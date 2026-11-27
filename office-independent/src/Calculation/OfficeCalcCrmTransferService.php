<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use PDO;
use Throwable;

/**
 * Persists an accepted CRM calculation as a locked work-budget baseline.
 * Caller creates the Office project in the SAME transaction through callback.
 * Database unique constraints prevent duplicate transfers across requests.
 */
final class OfficeCalcCrmTransferService
{
    public function __construct(private readonly PDO $db) {}

    public function transfer(array $acceptance, array $lines, callable $createProject, callable $verifyCrmAcceptance): array
    {
        return $this->run($acceptance,$lines,$createProject,$verifyCrmAcceptance,false);
    }

    /** Join the quotation handoff transaction; its caller owns commit/rollback. */
    public function transferInTransaction(array $acceptance,array $lines,callable $createProject,callable $verifyCrmAcceptance):array
    {
        return $this->run($acceptance,$lines,$createProject,$verifyCrmAcceptance,true);
    }

    private function run(array $acceptance,array $lines,callable $createProject,callable $verifyCrmAcceptance,bool $joined):array
    {
        if ($joined ? !$this->db->inTransaction() : $this->db->inTransaction()) {
            throw new DomainException('Transfer requires ownership of transaction');
        }
        require_once __DIR__ . '/OfficeCalcAcceptanceIdentity.php';
        require_once __DIR__ . '/OfficeCalcWorkBudgetHandoff.php';
        $key = OfficeCalcAcceptanceIdentity::key($acceptance);
        $budget = OfficeCalcWorkBudgetHandoff::prepare($acceptance, $lines);
        $hasDecimals = false;
        foreach ($lines as $line) {
            if (!is_array($line) || !array_key_exists('quantity_decimal', $line)) continue;
            $hasDecimals = true;
            foreach (['quantity_decimal', 'unit_cost_decimal', 'budget_decimal'] as $field) {
                if (!is_string($line[$field] ?? null)
                    || !preg_match('/^(?:0|[1-9][0-9]{0,17})(?:\.[0-9]{1,4})?$/D', $line[$field])) {
                    throw new DomainException('Invalid decimal work-budget field: '.$field);
                }
            }
            if ($line['quantity'] !== 1 || !function_exists('bcmul')
                || bccomp(bcmul($line['quantity_decimal'], $line['unit_cost_decimal'], 4), $line['budget_decimal'], 4) !== 0) {
                throw new DomainException('Decimal work-budget total mismatch.');
            }
            // The legacy cents column must match the approved decimal line total
            // rounded half-up once. Reject tampered or inconsistent payloads.
            $roundedCents = bcadd(bcmul($line['quantity_decimal'], $line['unit_cost_decimal'], 8), '0', 8);
            $roundedCents = bcadd(bcmul($roundedCents, '100', 8), '0.5', 0);
            if (bccomp($roundedCents, (string)$line['unit_cost_cents'], 0) !== 0) {
                throw new DomainException('Decimal budget cents do not match the source calculation.');
            }
        }
        if ($hasDecimals) {
            if (count(array_filter($lines, static fn($line): bool => is_array($line) && isset($line['quantity_decimal']))) !== count($lines)) {
                throw new DomainException('Cannot mix legacy and decimal budget lines.');
            }
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $columns = array_column(
                    $this->db->query('PRAGMA table_info(office_calc_work_budget_lines)')->fetchAll(PDO::FETCH_ASSOC),
                    'name'
                );
            } else {
                $columns = $this->db->query(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS " .
                    "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'office_calc_work_budget_lines'"
                )->fetchAll(PDO::FETCH_COLUMN);
            }
            foreach (['quantity_decimal', 'unit_cost_decimal', 'budget_decimal'] as $field) {
                if (!in_array($field, $columns, true)) throw new DomainException('Decimal work-budget schema not installed: '.$field);
            }
        }
        if ($this->db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new DomainException('Transfer requires PDO exception mode');
        }
        if (!$joined) $this->db->beginTransaction();
        try {
            // Verify persisted CRM acceptance and EXACT SOURCE LINES under the same transaction before any
            // project or budget write. The adapter must lock the opportunity row
            // and return true only for the exact accepted calculation/revision.
            if ($verifyCrmAcceptance($this->db, $acceptance, $lines) !== true) {
                throw new DomainException('CRM acceptance is not verified against persisted Office data');
            }
            if (!$this->db->inTransaction()) {
                throw new DomainException('CRM verifier ended the transfer transaction');
            }
            // Lock the existing opportunity row when present. A unique index
            // closes the concurrent-insert race when the row is not present.
            $existing = $this->db->prepare(
                'SELECT acceptance_key, office_project_id FROM office_calc_crm_acceptance_transfers ' .
                'WHERE crm_opportunity_id = ? FOR UPDATE'
            );
            $existing->execute([$acceptance['crm_opportunity_id']]);
            $prior = $existing->fetch(PDO::FETCH_ASSOC);
            if ($prior !== false) {
                if (!hash_equals((string)$prior['acceptance_key'], $key)) {
                    throw new DomainException('CRM opportunity already accepted under another revision');
                }
                throw new DomainException('CRM acceptance already transferred');
            }
            // Check the persisted Deal draft before creating any project or budget.
            require_once __DIR__.'/OfficeCalcDealRevisionGuard.php';
            $draftQuery = $this->db->prepare('SELECT id, calculation_id FROM office_calc_crm_drafts WHERE opportunity_id=? FOR UPDATE');
            $draftQuery->execute([$acceptance['crm_opportunity_id']]);
            $drafts = $draftQuery->fetchAll(PDO::FETCH_ASSOC);
            foreach ($drafts as $draft) {
                if ((string)$draft['calculation_id'] !== (string)$acceptance['calculation_id']) {
                    throw new DomainException('Accepted calculation does not match the Deal draft');
                }
                (new OfficeCalcDealRevisionGuard($this->db))->assertReviewed((int)$draft['id']);
            }
            // The verifier must also attest the stored source revision AND its
            // persisted calculation lines. Never trust request-provided amounts.
            // Project creation remains an injected native Office adapter; no
            // project or runtime routing is enabled by this service alone.
            $projectId = $createProject($this->db, $acceptance);
            if (!$this->db->inTransaction()) {
                throw new DomainException('Project adapter ended the transfer transaction');
            }
            if (!is_int($projectId) || $projectId < 1) {
                throw new DomainException('Project creation did not return a valid ID');
            }
            if (getenv('OFFICE_CRM_DEAL_MULTI_BUILDINGS_SCHEMA_VERIFIED')==='enabled') {
                require_once dirname(__DIR__).'/Buildings/OfficeDealProjectBuildingTransfer.php';
                (new \Brebo\Office\Buildings\OfficeDealProjectBuildingTransfer($this->db))->transfer((int)$acceptance['crm_opportunity_id'],$projectId);
            }
            $insert = $this->db->prepare(
                'INSERT INTO office_calc_crm_acceptance_transfers ' .
                '(crm_opportunity_id, acceptance_key, calculation_id, accepted_revision, office_project_id) ' .
                'VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $acceptance['crm_opportunity_id'], $key,
                $acceptance['calculation_id'], $acceptance['accepted_revision'], $projectId,
            ]);
            $transferId = (int)$this->db->lastInsertId();
            if ($transferId < 1) {
                throw new DomainException('Transfer was not persisted');
            }
            $baseline = $this->db->prepare(
                'INSERT INTO office_calc_work_budget_baselines (acceptance_transfer_id, version_number) VALUES (?, 1)'
            );
            $baseline->execute([$transferId]);
            $baselineId = (int)$this->db->lastInsertId();
            if ($baselineId < 1) {
                throw new DomainException('Work budget baseline was not persisted');
            }
            $lineInsert = $this->db->prepare(
                'INSERT INTO office_calc_work_budget_lines ' .
                '(baseline_id, source_line_id, cost_type, quantity, unit_cost_cents, budget_cents, activity_ref) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if ($hasDecimals) {
                $lineInsert = $this->db->prepare(
                    'INSERT INTO office_calc_work_budget_lines ' .
                    '(baseline_id, source_line_id, cost_type, quantity, unit_cost_cents, budget_cents, activity_ref, quantity_decimal, unit_cost_decimal, budget_decimal) ' .
                    'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
            }
            $scopeEnabled = getenv('OFFICE_WORK_BUDGET_BUILDING_SCOPE_VERIFIED') === 'enabled';
            $scopeBySource = [];
            if ($scopeEnabled) {
                $membership = $this->db->prepare('SELECT 1 FROM office_project_buildings WHERE project_id=? AND building_id=?');
                foreach ($lines as $line) {
                    if (!array_key_exists('building_id', $line)) throw new DomainException('Accepted building scope missing');
                    $building = $line['building_id'];
                    if ($building !== null) {
                        if (!is_int($building) || $building < 1) throw new DomainException('Invalid accepted building scope');
                        $membership->execute([$projectId,$building]);
                        if (!$membership->fetchColumn()) throw new DomainException('Budget building outside project');
                    }
                    $scopeBySource[$line['line_id']] = $building;
                }
                $scopeInsert = $this->db->prepare('INSERT INTO office_work_budget_building_scope (baseline_line_id,building_id) VALUES (?,?)');
            }
            foreach ($budget['items'] as $index => $item) {
                $values = [
                    $baselineId, $item['source_line_id'], $item['cost_type'],
                    $item['quantity'], $item['unit_cost_cents'],
                    $item['budget_cents'], $item['activity_ref'],
                ];
                if ($hasDecimals) {
                    $values[] = $lines[$index]['quantity_decimal'];
                    $values[] = $lines[$index]['unit_cost_decimal'];
                    $values[] = $lines[$index]['budget_decimal'];
                }
                $lineInsert->execute($values);
                if ($scopeEnabled) {
                    $lineId = (int)$this->db->lastInsertId();
                    if ($lineId < 1 || !array_key_exists($item['source_line_id'],$scopeBySource)) throw new DomainException('Budget scope source missing');
                    $scopeInsert->execute([$lineId,$scopeBySource[$item['source_line_id']]]);
                }
            }
            // Confirm canonical project and all written budget lines before
            // committing. Neither a generated ID nor a callback return value is
            // evidence of a durable project-bound locked budget.
            $check = $this->db->prepare(
                'SELECT t.office_project_id, b.locked_at, COUNT(l.id) AS line_count ' .
                'FROM office_calc_crm_acceptance_transfers t ' .
                'JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id = t.id ' .
                'LEFT JOIN office_calc_work_budget_lines l ON l.baseline_id = b.id ' .
                'WHERE t.id = ? AND b.id = ? AND t.acceptance_key = ? ' .
                'GROUP BY t.office_project_id, b.locked_at'
            );
            $check->execute([$transferId, $baselineId, $key]);
            $stored = $check->fetch(PDO::FETCH_ASSOC);
            if (!is_array($stored)
                || (int)$stored['office_project_id'] !== $projectId
                || !is_string($stored['locked_at']) || $stored['locked_at'] === ''
                || (int)$stored['line_count'] !== count($budget['items'])) {
                throw new DomainException('Persisted locked work budget verification failed');
            }
            // Compare every persisted value with the verified accepted source,
            // not merely the row count. Reject silent truncation or substitution.
            $readLines = $this->db->prepare(
                'SELECT source_line_id, cost_type, quantity, unit_cost_cents, budget_cents, activity_ref ' .
                ($hasDecimals ? ', quantity_decimal, unit_cost_decimal, budget_decimal ' : '') .
                'FROM office_calc_work_budget_lines WHERE baseline_id = ? ORDER BY source_line_id'
            );
            $readLines->execute([$baselineId]);
            $actualLines = $readLines->fetchAll(PDO::FETCH_ASSOC);
            $expectedLines = $budget['items'];
            usort($expectedLines, static fn(array $a, array $b): int =>
                strcmp($a['source_line_id'], $b['source_line_id']));
            if (count($actualLines) !== count($expectedLines)) {
                throw new DomainException('Persisted work budget line count differs');
            }
            $decimalBySource = [];
            if ($hasDecimals) {
                foreach ($lines as $acceptedLine) {
                    $decimalBySource[$acceptedLine['line_id']] = $acceptedLine;
                }
            }
            foreach ($expectedLines as $index => $expected) {
                $actual = $actualLines[$index] ?? null;
                if (!is_array($actual)
                    || (string)$actual['source_line_id'] !== $expected['source_line_id']
                    || (string)$actual['cost_type'] !== $expected['cost_type']
                    || !self::equalAmount($actual['quantity'], $expected['quantity'], 4)
                    || !self::equalAmount($actual['unit_cost_cents'], $expected['unit_cost_cents'], 2)
                    || !self::equalAmount($actual['budget_cents'], $expected['budget_cents'], 0)
                    || $actual['activity_ref'] !== $expected['activity_ref']) {
                    throw new DomainException('Persisted work budget line differs from accepted source');
                }
                if ($hasDecimals) {
                    $decimal = $decimalBySource[$expected['source_line_id']] ?? null;
                    if (!is_array($decimal)) {
                        throw new DomainException('Missing accepted decimal source line');
                    }
                    foreach (['quantity_decimal', 'unit_cost_decimal', 'budget_decimal'] as $field) {
                        if (!self::equalAmount($actual[$field] ?? null, $decimal[$field] ?? null, 4)) {
                            throw new DomainException('Persisted decimal work budget differs from accepted source: '.$field);
                        }
                    }
                }
            }
            if ($scopeEnabled) {
                $scopeRead = $this->db->prepare('SELECT l.source_line_id,s.building_id FROM office_calc_work_budget_lines l JOIN office_work_budget_building_scope s ON s.baseline_line_id=l.id WHERE l.baseline_id=?');
                $scopeRead->execute([$baselineId]);
                $actualScope = [];
                foreach ($scopeRead->fetchAll(PDO::FETCH_ASSOC) as $row) $actualScope[$row['source_line_id']] = $row['building_id'] === null ? null : (int)$row['building_id'];
                ksort($actualScope);
                ksort($scopeBySource);
                if ($actualScope !== $scopeBySource) throw new DomainException('Persisted building scope differs');
            }
            $projectCheck = $this->db->prepare('SELECT 1 FROM office_projects WHERE id = ?');
            $projectCheck->execute([$projectId]);
            if ($projectCheck->fetchColumn() === false) {
                throw new DomainException('Native Office project was not persisted');
            }
            if (!$this->db->inTransaction()) {
                throw new DomainException('Transfer transaction ended before commit');
            }
            if (!$joined) $this->db->commit();
            return ['project_id' => $projectId, 'work_budget_id' => $baselineId, 'acceptance_key' => $key];
        } catch (Throwable $error) {
            if (!$joined && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
    private static function equalAmount(mixed $actual, mixed $expected, int $scale): bool
    {
        if ((!is_string($actual) && !is_int($actual)) || (!is_string($expected) && !is_int($expected))) {
            return false;
        }
        if (!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', (string)$actual)
            || !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', (string)$expected)
            || !function_exists('bccomp')) {
            return false;
        }
        return bccomp((string)$actual, (string)$expected, max(8, $scale)) === 0;
    }
}
