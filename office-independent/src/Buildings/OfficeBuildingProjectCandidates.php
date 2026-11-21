<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;

/**
 * Read-only project links for a previously authorized building.
 * The caller must check BOTH building and project permissions.
 */
final class OfficeBuildingProjectCandidates
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param callable(string):bool $canViewBuilding
     * @param callable(string):bool $canViewProject
     * @return list<array{project_id:string,building_id:string}>
     */
    public function forBuilding(
        string $buildingId,
        callable $canViewBuilding,
        callable $canViewProject,
        int $limit = 25
    ): array {
        if (!preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId) || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('invalid_building_project_query');
        }
        if (!$canViewBuilding($buildingId)) return [];

        $stmt = $this->db->prepare("SELECT pb.project_id,pb.building_id
            FROM office_project_buildings pb
            INNER JOIN office_buildings b ON b.id=pb.building_id AND b.active=1
            WHERE pb.building_id=:building
            ORDER BY pb.project_id DESC LIMIT 500");
        $stmt->execute([':building'=>$buildingId]);
        $result=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $projectId=(string)$row['project_id'];
            if (!$canViewProject($projectId)) continue;
            $result[]=['project_id'=>$projectId,'building_id'=>(string)$row['building_id']];
            if (count($result)>=$limit) break;
        }
        return $result;
    }
}
