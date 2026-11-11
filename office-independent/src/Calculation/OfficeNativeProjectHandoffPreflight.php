<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Read-only readiness gate for native Office project creation. */
final class OfficeNativeProjectHandoffPreflight
{
    public static function inspect(PDO $db): array
    {
        $required = [
            'office_projects' => ['id', 'title'],
            'office_project_memberships' => ['project_id', 'operator_id', 'permission'],
            'office_project_buildings' => ['project_id', 'building_id'],
            'office_crm_calculation_acceptances' => ['opportunity_id', 'calculation_id', 'accepted_revision', 'status'],
            'office_calc_crm_acceptance_transfers' => ['crm_opportunity_id', 'office_project_id', 'acceptance_key'],
            'office_calc_work_budget_baselines' => ['id', 'acceptance_transfer_id'],
            'office_calc_work_budget_lines' => ['baseline_id', 'source_line_id', 'cost_type', 'quantity', 'unit_cost_cents', 'budget_cents'],
        ];
        $missing = [];
        foreach ($required as $table => $columns) {
            $stmt = $db->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            $present = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns as $column) {
                if (!in_array($column, $present, true)) $missing[] = $table . '.' . $column;
            }
        }
        return ['ready' => $missing === [], 'missing' => $missing];
    }
}
