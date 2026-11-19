<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/**
 * Read-only canonical project -> building -> housing-type snapshot for Calc.
 * Counts remain in Office building/project assignments, never in Calc.
 */
final class OfficeCalcBuildingTruthReader
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{project_id:int,buildings:list<array{id:int,name:string,housing_types:list<array{id:int,code:string,name:string,dwelling_count:int}>}>} */
    public function forProject(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project ID');
        }

        $statement = $this->db->prepare(
            'SELECT b.id AS building_id, b.name AS building_name,
                    ht.id AS housing_type_id, ht.code AS housing_type_code,
                    ht.name AS housing_type_name, pht.dwelling_count
             FROM office_project_buildings pb
             INNER JOIN office_buildings b ON b.id = pb.building_id
             LEFT JOIN office_project_housing_types pht
               ON pht.project_id = pb.project_id AND pht.building_id = b.id
             LEFT JOIN office_housing_types ht
               ON ht.id = pht.housing_type_id AND ht.building_id = b.id
             WHERE pb.project_id = :project_id
             ORDER BY b.name, b.id, ht.name, ht.id'
        );
        $statement->execute([':project_id' => $projectId]);

        $buildings = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $buildingId = (int) $row['building_id'];
            if (!isset($buildings[$buildingId])) {
                $buildings[$buildingId] = [
                    'id' => $buildingId,
                    'name' => (string) $row['building_name'],
                    'housing_types' => [],
                ];
            }
            if ($row['housing_type_id'] === null) {
                continue;
            }
            $buildings[$buildingId]['housing_types'][] = [
                'id' => (int) $row['housing_type_id'],
                'code' => (string) $row['housing_type_code'],
                'name' => (string) $row['housing_type_name'],
                'dwelling_count' => (int) $row['dwelling_count'],
            ];
        }

        return ['project_id' => $projectId, 'buildings' => array_values($buildings)];
    }
}
