<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Read-only database gate: never install or mutate schema in a web request. */
final class OfficeCalcWorkBudgetSchemaPreflight
{
    private const REQUIRED = [
        'office_calc_crm_acceptance_transfers' => [
            'id', 'crm_opportunity_id', 'acceptance_key', 'calculation_id',
            'accepted_revision', 'office_project_id',
        ],
        'office_calc_work_budget_baselines' => [
            'id', 'acceptance_transfer_id', 'version_number', 'locked_at',
        ],
        'office_calc_work_budget_lines' => [
            'id', 'baseline_id', 'source_line_id', 'cost_type',
            'quantity', 'unit_cost_cents', 'budget_cents',
        ],
    ];

    public static function verify(PDO $db): array
    {
        $result = [];
        foreach (self::REQUIRED as $table => $requiredColumns) {
            $stmt = $db->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS ' .
                'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
            );
            $stmt->execute(['table_name' => $table]);
            $actual = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $result[$table] = [
                'present' => $actual !== [],
                'missing_columns' => array_values(array_diff($requiredColumns, $actual)),
            ];
        }
        return [
            'verified' => array_reduce(
                $result,
                static fn(bool $ok, array $entry): bool =>
                    $ok && $entry['present'] && $entry['missing_columns'] === [],
                true
            ),
            'tables' => $result,
        ];
    }
}
