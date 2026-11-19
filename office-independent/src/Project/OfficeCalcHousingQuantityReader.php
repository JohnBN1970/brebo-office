<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/**
 * Read-only per-housing-type quantity handoff for the existing Calc workbench.
 * Source lines belong to canonical project scopes. No pricing or Calc writes.
 */
final class OfficeCalcHousingQuantityReader
{
    public function __construct(private readonly PDO $db) {}

    public function forProject(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project ID');
        }
        $query = $this->db->prepare(
            'SELECT s.id AS scope_id, s.building_id, s.housing_type_id,
                    s.calc_calculation_id, ht.code AS housing_code, ht.name AS housing_name,
                    pht.dwelling_count, q.line_code, q.description, q.unit,
                    q.quantity_per_dwelling
             FROM office_project_calculation_scopes s
             INNER JOIN office_project_buildings pb
               ON pb.project_id = s.project_id AND pb.building_id = s.building_id
             INNER JOIN office_project_housing_types pht
               ON pht.project_id = s.project_id
              AND pht.building_id = s.building_id
              AND pht.housing_type_id = s.housing_type_id
             INNER JOIN office_housing_types ht
               ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
             LEFT JOIN office_scope_quantity_lines q ON q.scope_id = s.id
             WHERE s.project_id = :project_id AND s.scope_kind = :scope_kind
             ORDER BY s.id, q.line_code, q.id'
        );
        $query->execute([':project_id' => $projectId, ':scope_kind' => 'housing_type']);
        $scopes = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scopeId = (int) $row['scope_id'];
            if (!isset($scopes[$scopeId])) {
                $scopes[$scopeId] = [
                    'scope_id' => $scopeId,
                    'building_id' => (int) $row['building_id'],
                    'housing_type_id' => (int) $row['housing_type_id'],
                    'housing_code' => (string) $row['housing_code'],
                    'housing_name' => (string) $row['housing_name'],
                    'dwelling_count' => (int) $row['dwelling_count'],
                    'calculation_id' => $row['calc_calculation_id'] === null ? null : (int) $row['calc_calculation_id'],
                    'lines' => [],
                ];
            }
            if ($row['line_code'] === null) {
                continue;
            }
            $scopes[$scopeId]['lines'][] = [
                'code' => (string) $row['line_code'],
                'description' => (string) $row['description'],
                'unit' => (string) $row['unit'],
                // Preserve decimal precision for Calc; no floating-point arithmetic.
                'quantity_per_dwelling' => (string) $row['quantity_per_dwelling'],
            ];
        }
        return ['project_id' => $projectId, 'scopes' => array_values($scopes)];
    }
}
