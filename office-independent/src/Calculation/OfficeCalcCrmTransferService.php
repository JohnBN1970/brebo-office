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
        if ($this->db->inTransaction()) {
            throw new DomainException('Transfer requires ownership of transaction');
        }
        require_once __DIR__ . '/OfficeCalcAcceptanceIdentity.php';
        require_once __DIR__ . '/OfficeCalcWorkBudgetHandoff.php';
        $key = OfficeCalcAcceptanceIdentity::key($acceptance);
        $budget = OfficeCalcWorkBudgetHandoff::prepare($acceptance, $lines);
        if ($this->db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new DomainException('Transfer requires PDO exception mode');
        }
        $this->db->beginTransaction();
        try {
            // Verify persisted CRM acceptance and EXACT SOURCE LINES under the same transaction before any
            // project or budget write. The adapter must lock the opportunity row
            // and return true only for the exact accepted calculation/revision.
            if ($verifyCrmAcceptance($this->db, $acceptance, $lines) !== true) {
                throw new DomainException('CRM acceptance is not verified against persisted Office data');
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
            // The verifier must also attest the stored source revision AND its
            // persisted calculation lines. Never trust request-provided amounts.
            // Project creation remains an injected native Office adapter; no
            // project or runtime routing is enabled by this service alone.
            $projectId = $createProject($this->db, $acceptance);
            if (!is_int($projectId) || $projectId < 1) {
                throw new DomainException('Project creation did not return a valid ID');
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
            foreach ($budget['items'] as $item) {
                $lineInsert->execute([
                    $baselineId, $item['source_line_id'], $item['cost_type'],
                    $item['quantity'], $item['unit_cost_cents'],
                    $item['budget_cents'], $item['activity_ref'],
                ]);
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
            $projectCheck = $this->db->prepare('SELECT 1 FROM office_projects WHERE id = ?');
            $projectCheck->execute([$projectId]);
            if ($projectCheck->fetchColumn() === false) {
                throw new DomainException('Native Office project was not persisted');
            }
            $this->db->commit();
            return ['project_id' => $projectId, 'work_budget_id' => $baselineId, 'acceptance_key' => $key];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
