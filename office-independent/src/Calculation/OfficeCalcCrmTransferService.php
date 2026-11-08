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
        $this->db->beginTransaction();
        try {
            // Verify persisted CRM acceptance under the same transaction before any
            // project or budget write. The adapter must lock the opportunity row
            // and return true only for the exact accepted calculation/revision.
            if ($verifyCrmAcceptance($this->db, $acceptance) !== true) {
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
            $baseline = $this->db->prepare(
                'INSERT INTO office_calc_work_budget_baselines (acceptance_transfer_id, version_number) VALUES (?, 1)'
            );
            $baseline->execute([$transferId]);
            $baselineId = (int)$this->db->lastInsertId();
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
