<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;

/**
 * Read-only handoff status. A won opportunity is not proof that a project
 * or locked calculation work budget has been transferred.
 */
final class OfficeCrmWorkBudgetHandoffStatus
{
    public function __construct(private readonly PDO $db) {}

    public function forOpportunity(int $opportunityId): array
    {
        if ($opportunityId < 1) {
            throw new \InvalidArgumentException('Invalid opportunity');
        }
        $stmt = $this->db->prepare(
            'SELECT o.stage, o.status, t.office_project_id, b.id AS baseline_id, b.locked_at ' .
            'FROM office_crm_opportunities o ' .
            'LEFT JOIN office_calc_crm_acceptance_transfers t ON t.crm_opportunity_id = CAST(o.id AS CHAR) ' .
            'LEFT JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id = t.id ' .
            'WHERE o.id = ? ORDER BY b.version_number DESC LIMIT 1'
        );
        $stmt->execute([$opportunityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['state' => 'not_found', 'project_id' => null, 'baseline_id' => null];
        }
        $projectId = isset($row['office_project_id']) ? (int)$row['office_project_id'] : null;
        $baselineId = isset($row['baseline_id']) && $row['locked_at'] !== null ? (int)$row['baseline_id'] : null;
        $state = $baselineId !== null && $projectId !== null ? 'transferred_locked'
            : ($projectId !== null ? 'project_without_locked_budget'
            : ($row['stage'] === 'won' ? 'won_not_transferred' : 'not_yet_accepted'));
        return ['state' => $state, 'project_id' => $projectId, 'baseline_id' => $baselineId];
    }
}
