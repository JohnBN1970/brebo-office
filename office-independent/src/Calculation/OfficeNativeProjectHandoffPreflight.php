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
        $tableEngines = [];
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
        // Atomic handoff requires transactional tables, not merely present columns.
        $engineQuery = $db->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        foreach (array_keys($required) as $table) {
            $engineQuery->execute([$table]);
            $engine = $engineQuery->fetchColumn();
            $tableEngines[$table] = $engine === false ? null : (string)$engine;
            if ($engine !== false && strcasecmp((string)$engine, 'InnoDB') !== 0) {
                $missing[] = $table . '.transactional_engine';
            }
        }
        // Column presence alone does not prove that a project can be created.
        // Return the exact table columns to review required native project fields.
        $projectColumns = $db->query(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'office_projects' ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC);
        $requiredProjectInputs = [];
        foreach ($projectColumns as $column) {
            if ($column['IS_NULLABLE'] === 'NO'
                && $column['COLUMN_DEFAULT'] === null
                && !str_contains(strtolower((string)$column['EXTRA']), 'auto_increment')
                && !str_contains(strtolower((string)$column['EXTRA']), 'generated')) {
                $requiredProjectInputs[] = (string)$column['COLUMN_NAME'];
            }
        }
        return [
            // Operational readiness must remain false until the native factory is wired.
            'ready' => false,
            'schema_ready' => $missing === [],
            'missing' => $missing,
            'table_engines' => $tableEngines,
            'native_project_required_inputs' => $requiredProjectInputs,
            'project_factory_connected' => false,
        ];
    }
}
