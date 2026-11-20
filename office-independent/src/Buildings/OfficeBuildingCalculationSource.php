<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Read-only calculation source preview. This never creates a calculation or
 * transfers prices; the calculation workflow must explicitly accept the source.
 */
final class OfficeBuildingCalculationSource {
  public const SCOPES = ['building','demarcation','dwelling_type','address'];

  public function __construct(private PDO $db) {}

  public function preview(int $buildingId,string $scope,?int $scopeId=null): array {
    if ($buildingId<1 || !in_array($scope,self::SCOPES,true)) {
      throw new InvalidArgumentException('invalid_calculation_source');
    }
    if ($scope==='building' && $scopeId!==null) {
      throw new InvalidArgumentException('building_scope_has_no_child_id');
    }
    if ($scope!=='building' && ($scopeId===null || $scopeId<1)) {
      throw new InvalidArgumentException('missing_scope_id');
    }

    // All source checks are building-scoped; never read another building's items.
    $checks=[
      'building'=>'SELECT id FROM office_buildings WHERE id=?',
      'demarcation'=>'SELECT id FROM office_building_demarcations WHERE id=? AND building_id=?',
      'dwelling_type'=>'SELECT id FROM office_building_dwelling_types WHERE id=? AND building_id=?',
      'address'=>'SELECT id FROM office_building_addresses WHERE id=? AND building_id=?',
    ];
    $check=$this->db->prepare($checks[$scope]);
    $check->execute($scope==='building'?[$buildingId]:[$scopeId,$buildingId]);
    if (!$check->fetchColumn()) throw new InvalidArgumentException('source_not_in_building');

    $where='i.building_id=?';
    $params=[$buildingId];
    if ($scope==='demarcation') {
      $where.=' AND i.demarcation_id=?'; $params[]=$scopeId;
    } elseif ($scope==='dwelling_type') {
      $where.=' AND i.dwelling_type_id=?'; $params[]=$scopeId;
    } elseif ($scope==='address') {
      $where.=' AND i.address_id=?'; $params[]=$scopeId;
    }
    $sql="SELECT i.component_code,i.description,i.unit,
      SUM(i.quantity) AS quantity,COUNT(*) AS source_lines
      FROM office_building_inventory_items i
      WHERE $where
      GROUP BY i.component_code,i.description,i.unit
      ORDER BY i.component_code,i.description,i.unit LIMIT 501";
    $query=$this->db->prepare($sql);
    $query->execute($params);
    $rows=$query->fetchAll(PDO::FETCH_ASSOC);
    // A partial inventory must never be silently transferred to a priced calculation.
    if (count($rows)>500) throw new RuntimeException('inventory_source_too_large');
    return $rows;
  }
}
