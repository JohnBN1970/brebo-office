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
$key = str_repeat('a',64);
$idempotent = ['building_id'=>$one,'zone_name'=>'Dubbele aanvraag','latitude'=>51.52,'longitude'=>4.32,'radius_metres'=>120];
$first = $zones->createOnce($idempotent,$key,$buildingAccess);
$second = $zones->createOnce($idempotent,$key,$buildingAccess);
if ($first!==$second) throw new RuntimeException('Idempotent retry created another zone');
$conflict=false;
try {
  $zones->createOnce(array_replace($idempotent,['zone_name'=>'Andere aanvraag']),$key,$buildingAccess);
} catch (InvalidArgumentException $e) {
  $conflict=$e->getMessage()==='zone_request_key_conflict';
}
if (!$conflict) throw new RuntimeException('Reused request key accepted different payload');
$changes=['zone_name'=>'Bijgewerkte zone','latitude'=>51.53,'longitude'=>4.33,'radius_metres'=>180];
if (!$zones->update($first,1,$changes,$buildingAccess)) throw new RuntimeException('MySQL zone update failed');
if ($zones->update($first,1,array_replace($changes,['zone_name'=>'Stale']),$buildingAccess)) throw new RuntimeException('MySQL stale revision accepted');
$readback=$zones->forBuilding($one,$buildingAccess);
$updated=array_values(array_filter($readback,static fn(array $row):bool=>(string)$row['id']===$first));
if (count($updated)!==1 || (int)$updated[0]['revision']!==2 || $updated[0]['zone_name']!=='Bijgewerkte zone') {
  throw new RuntimeException('MySQL zone update readback mismatch');
}
$deniedUpdate=false;
try {
  $zones->update($first,2,array_replace($changes,['zone_name'=>'Unauthorized']),static fn(string $id):bool=>false);
} catch (InvalidArgumentException $e) {
  $deniedUpdate=$e->getMessage()==='unauthorized_building_zone';
}
if (!$deniedUpdate) throw new RuntimeException('MySQL unauthorized zone update accepted');
$invalidRadius=false;
try { $zones->update($first,2,array_replace($changes,['radius_metres'=>5001]),$buildingAccess); }
catch (InvalidArgumentException $e) { $invalidRadius=true; }
if (!$invalidRadius) throw new RuntimeException('MySQL invalid radius accepted');
// Exercise the actual signed form -> service -> repository chain, rather than
// only direct repository calls. Temporary identities stay in this CI connection.
foreach (['Intake/WebsiteCrmOperatorContext','Inzet/OnSiteOfficeZoneFormToken',
  'Inzet/OnSiteOfficeCircleCreateService','Inzet/OnSiteOfficeZonePostAction',
  'Inzet/OnSiteOfficeCircleUpdateService','Inzet/OnSiteOfficeZoneUpdatePostAction'] as $class) {
  require_once dirname(__DIR__).'/src/'.$class.'.php';
}
$db->exec('CREATE TEMPORARY TABLE office_projects (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY, active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (project_id BIGINT, operator_id VARCHAR(128), permission VARCHAR(20)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_projects VALUES (18)");
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager-uuid',1),('reader-uuid',1)");
$db->exec("INSERT INTO office_project_memberships VALUES (18,'manager-uuid','manage'),(18,'reader-uuid','read')");
$actor=new \Brebo\Office\Intake\WebsiteCrmOperatorContext('manager-uuid','ci-session',false);
$secret=str_repeat('s',32); $now=time();
$token=\Brebo\Office\Inzet\OnSiteOfficeZoneFormToken::issue($actor,'18',$one,$secret,$now);
$post=['project_id'=>'18','building_id'=>$one,'zone_name'=>'Signed form',
  'latitude'=>'51.5','longitude'=>'4.3','radius_metres'=>'155.25','form_token'=>$token];
$action=new \Brebo\Office\Inzet\OnSiteOfficeZonePostAction(new \Brebo\Office\Inzet\OnSiteOfficeCircleCreateService($db));
$send=fn(array $data)=>$action->handle('POST','brebobv.nl','brebobv.nl','https://brebobv.nl',
  'application/x-www-form-urlencoded',http_build_query($data),$actor,$secret,$now);
$created=$send($post); $retry=$send($post);
if ($created!==$retry) throw new RuntimeException('Signed form retry created a duplicate zone');
$edit=new \Brebo\Office\Inzet\OnSiteOfficeZoneUpdatePostAction(new \Brebo\Office\Inzet\OnSiteOfficeCircleUpdateService($db));
$editPost=$post+['zone_id'=>$created['zone_id'],'revision'=>'1'];
$edit->handle('application/x-www-form-urlencoded',http_build_query($editPost),$actor,$secret,$now);
$row=$db->query('SELECT radius_metres,revision FROM office_building_zones WHERE id='.(int)$created['zone_id'])->fetch(PDO::FETCH_ASSOC);
if ((float)$row['radius_metres']!==155.25 || (int)$row['revision']!==2) throw new RuntimeException('Signed update did not preserve exact radius');
$stale=false;
try { $edit->handle('application/x-www-form-urlencoded',http_build_query($editPost),$actor,$secret,$now); }
catch (RuntimeException $e) { $stale=$e->getMessage()==='zone_revision_conflict'; }
if (!$stale) throw new RuntimeException('Signed stale edit accepted');
$db->exec("UPDATE office_project_memberships SET permission='read' WHERE operator_id='manager-uuid'");
$revoked=false;
try { $send($post); } catch (InvalidArgumentException $e) { $revoked=$e->getMessage()==='unauthorized_building_zone'; }
if (!$revoked || $db->inTransaction()) throw new RuntimeException('Revoked membership retry not rejected and rolled back');
echo "Office building-owned zone MySQL checks passed\n";
echo "Signed form create/retry/edit/readback/stale/revoked-membership checks passed\n";
