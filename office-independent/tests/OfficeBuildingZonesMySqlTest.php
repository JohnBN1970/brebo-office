<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingSchema.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingZoneSchema.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingRepository.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingZoneRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteBuildingZoneAccess.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentGate.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAutomaticEventValidator.php';

$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated MySQL CI database required');
}
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES => false
]);
foreach (['office_building_zones','office_project_buildings','office_buildings'] as $table) {
  $db->exec('DROP TABLE IF EXISTS '.$table);
}
foreach (\Brebo\Office\Buildings\OfficeBuildingSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Buildings\OfficeBuildingZoneSchema::statements() as $sql) $db->exec($sql);
$buildings = new \Brebo\Office\Buildings\OfficeBuildingRepository($db);
$zones = new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db);
$one = $buildings->create(['building_name'=>'Testgebouw A','city'=>'Breda']);
$two = $buildings->create(['building_name'=>'Testgebouw B','city'=>'Breda']);
$projectAccess = static fn(string $id): bool => in_array($id,['17','18'],true);
$buildings->linkProject('17',$one,$projectAccess);
$buildings->linkProject('18',$one,$projectAccess);
$buildings->linkProject('18',$two,$projectAccess);
$buildingAccess = static fn(string $id): bool => $id===$one;
$zoneId = $zones->create(['building_id'=>$one,'zone_name'=>'Hoofdingang','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>150],$buildingAccess);
if (count($zones->forBuilding($one,$buildingAccess))!==1) throw new RuntimeException('Building zone not found');
if (count($zones->forProject('17',$projectAccess))!==1 || count($zones->forProject('18',$projectAccess))!==1) {
  throw new RuntimeException('Zone not shared across linked projects');
}
$adapter = new \Brebo\Office\Inzet\OnSiteBuildingZoneAccess($zones,$projectAccess);
if (!$adapter->callback()('17',$zoneId) || !$adapter->callback()('18',$zoneId)
  || $adapter->callback()('19',$zoneId)) throw new RuntimeException('Project zone authorization incorrect');
// A valid project assignment alone must not grant access to an unrelated building zone.
$otherAccess = static fn(string $id): bool => $id===$two;
$otherZoneId = $zones->create(['building_id'=>$two,'zone_name'=>'Achterterrein','latitude'=>51.51,'longitude'=>4.31,'radius_metres'=>100],$otherAccess);
if ($adapter->callback()('17',$otherZoneId) || !$adapter->callback()('18',$otherZoneId)) {
  throw new RuntimeException('Unlinked building zone accessible from project');
}
// Disabling a building zone must revoke all project memberships immediately.
$db->prepare('UPDATE office_building_zones SET active=0 WHERE id=?')->execute([$zoneId]);
if ($adapter->callback()('17',$zoneId) || $adapter->callback()('18',$zoneId)) {
  throw new RuntimeException('Inactive building zone still authorizes presence');
}
$db->prepare('UPDATE office_building_zones SET active=1 WHERE id=?')->execute([$zoneId]);
if (!$adapter->callback()('17',$zoneId)) throw new RuntimeException('Reactivated zone not accessible');
// Exercise the same validated transition and assignment gate used by device presence.
$assignment = new class($adapter) implements \Brebo\Office\Inzet\OnSiteAssignmentRepository {
  public function __construct(private \Brebo\Office\Inzet\OnSiteBuildingZoneAccess $access) {}
  public function isAssigned(string $employee, string $project, string $zone): bool {
    return $employee === '42' && in_array($project, ['17','18'], true)
      && $this->access->isActiveForProject($project, $zone);
  }
};
$gate = new \Brebo\Office\Inzet\OnSiteAssignmentGate($assignment);
$validator = new \Brebo\Office\Inzet\OnSiteAutomaticEventValidator();
foreach (['in','out'] as $transition) {
  $event = $validator->validate(['project_id'=>'17','zone_id'=>$zoneId,
    'kind'=>$transition,'occurred_at'=>'2026-10-20T08:00:00Z']);
  if (!$gate->authorize('42',$event) || $gate->authorize('43',$event)) {
    throw new RuntimeException('Employee assignment gate failed for '.$transition);
  }
  $event['zone_id'] = $otherZoneId;
  if ($gate->authorize('42',$event)) throw new RuntimeException('Unlinked zone accepted for '.$transition);
}
$db->exec('UPDATE office_project_buildings SET project_id=19 WHERE project_id=17');
if ($adapter->callback()('17',$zoneId)) throw new RuntimeException('Removed project link retained zone access');
$denied = false;
try {
  $zones->create(['building_id'=>$two,'zone_name'=>'Unauthorized','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>100],$buildingAccess);
} catch (InvalidArgumentException $e) {
  $denied = $e->getMessage()==='unauthorized_building_zone';
}
if (!$denied) throw new RuntimeException('Unauthorized building zone created');
echo "Office building-owned zone MySQL checks passed\n";
