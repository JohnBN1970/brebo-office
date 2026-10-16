<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;

/**
 * Read-only canonical project building and housing-type snapshot for Calc.
 * Quantities are never copied or edited by the calculation.
 */
final class OfficeCalcBuildingTruthReader {
  public function __construct(private readonly PDO $db) {}

  public function forProject(int $projectId): array {
    if ($projectId < 1) throw new RuntimeException('Ongeldig project.');
    $buildingsQuery = $this->db->prepare(
      'SELECT b.id, b.code, b.name
       FROM office_project_buildings pb
       INNER JOIN office_buildings b ON b.id = pb.building_id
       WHERE pb.project_id = :project ORDER BY b.id'
    );
    $buildingsQuery->execute([':project' => $projectId]);
    $buildings = [];
    foreach ($buildingsQuery->fetchAll(PDO::FETCH_ASSOC) as $building) {
      $id = (int) $building['id'];
      $buildings[$id] = [
        'id' => $id,
        'code' => (string) $building['code'],
        'name' => (string) $building['name'],
        'housing_types' => [],
      ];
    }
    $typesQuery = $this->db->prepare(
      'SELECT pht.building_id, ht.id, ht.code, ht.name, pht.dwelling_count
       FROM office_project_housing_types pht
       INNER JOIN office_housing_types ht
         ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
       INNER JOIN office_project_buildings pb
         ON pb.project_id = pht.project_id AND pb.building_id = pht.building_id
       WHERE pht.project_id = :project ORDER BY pht.building_id, ht.id'
    );
    $typesQuery->execute([':project' => $projectId]);
    foreach ($typesQuery->fetchAll(PDO::FETCH_ASSOC) as $type) {
      if (!ctype_digit((string) $type['dwelling_count']) || (int) $type['dwelling_count'] < 1) {
        throw new RuntimeException('Ongeldig aantal woningen in Gebouwwaarheid.');
      }
      $buildingId = (int) $type['building_id'];
      if (!isset($buildings[$buildingId])) throw new RuntimeException('Woningtype zonder gekoppeld gebouw.');
      $buildings[$buildingId]['housing_types'][] = [
        'id' => (int) $type['id'],
        'code' => (string) $type['code'],
        'name' => (string) $type['name'],
        'dwelling_count' => (int) $type['dwelling_count'],
      ];
    }
    return [
      'contract' => 'brebo-office-calc-building-truth-v1',
      'project_id' => $projectId,
      'buildings' => array_values($buildings),
    ];
  }
}
