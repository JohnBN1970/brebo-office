<?php
declare(strict_types=1);
// Run after OfficeOnSiteAuthenticatedEventMySqlTest.php in isolated CI only.
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated MySQL CI database required');
}
$root = dirname(__DIR__) . '/src/';
foreach ([
  'Buildings/OfficeBuildingSchema.php','Buildings/OfficeBuildingZoneSchema.php',
  'Buildings/OfficeBuildingRepository.php','Buildings/OfficeBuildingZoneRepository.php',
  'Inzet/OnSiteBuildingZoneAccess.php','Inzet/OnSiteDeviceTokenRepository.php',
  'Inzet/OnSiteAutomaticEventValidator.php','Inzet/OnSiteAssignmentRepository.php',
  'Inzet/OnSiteAssignmentGate.php','Inzet/OnSiteOfficeTeamAssignmentRepository.php',
  'Inzet/OnSiteEventInbox.php','Inzet/OnSiteAutomaticIngest.php',
  'Inzet/OnSiteAuthenticatedEventService.php','Inzet/OnSitePresenceRequestService.php',
  'Inzet/OnSitePresenceApiHandler.php'
] as $file) require_once $root . $file;
$db = new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false
]);
foreach (['office_building_zones','office_project_buildings','office_buildings'] as $table) {
  $db->exec('DROP TABLE IF EXISTS '.$table);
}
foreach (\Brebo\Office\Buildings\OfficeBuildingSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Buildings\OfficeBuildingZoneSchema::statements() as $sql) $db->exec($sql);
$buildings = new \Brebo\Office\Buildings\OfficeBuildingRepository($db);
$zones = new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db);
$projectAccess = static fn(string $project):bool => $project==='17';
$buildingId = $buildings->create(['code'=>'TEST-FICTIEF','building_name'=>'Fictief testgebouw','city'=>'Breda']);
$buildings->linkProject('17',$buildingId,$projectAccess);
$zoneId = $zones->create([
  'building_id'=>$buildingId,'zone_name'=>'Testpoort',
  'latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>100
],static fn(string $id):bool => $id===$buildingId);
$membership = new \Brebo\Office\Inzet\OnSiteBuildingZoneAccess($zones,$projectAccess);
$token = str_repeat('k',48);
$db->prepare('INSERT INTO office_onsite_device_tokens (token_hash,person_id) VALUES (?,?)')
  ->execute([hash('sha256',$token),101]);
$assignment = new \Brebo\Office\Inzet\OnSiteOfficeTeamAssignmentRepository($db,$membership->callback());
$handler = new \Brebo\Office\Inzet\OnSitePresenceApiHandler(
  new \Brebo\Office\Inzet\OnSitePresenceRequestService(),
  new \Brebo\Office\Inzet\OnSiteAuthenticatedEventService(
    new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db),
    new \Brebo\Office\Inzet\OnSiteAutomaticIngest(
      new \Brebo\Office\Inzet\OnSiteAutomaticEventValidator(),
      new \Brebo\Office\Inzet\OnSiteAssignmentGate($assignment),
      new \Brebo\Office\Inzet\OnSiteEventInbox($db)
    )
  )
);
$event = ['project_id'=>'17','zone_id'=>$zoneId,'kind'=>'in','occurred_at'=>'2026-10-20T08:00:00Z'];
$submit = static fn(string $token,string $id,array $event):array =>
  $handler->submit(['event_id'=>$id,'event'=>$event],$token);
$before = (int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn();
$inId = 'native_building_arrival_0001';
$first = $submit($token,$inId,$event);
$repeat = $submit($token,$inId,$event);
if ($first['status']!==202 || $first['body']['duplicate']!==false
  || $repeat['status']!==202 || $repeat['body']['duplicate']!==true) {
  throw new RuntimeException('Building-owned arrival or idempotency failed');
}
$current=$zones->forProjectZone('17',$zoneId,$projectAccess);
if (!is_array($current)) throw new RuntimeException('Zone unavailable before edit');
$revision=(int)$current['revision'];
$edit=['zone_name'=>'Testpoort aangepast','latitude'=>'51.501','longitude'=>'4.301','radius_metres'=>'110'];
$authorized=static fn(string $id):bool=>$id===$buildingId;
if (!$zones->update($zoneId,$buildingId,$revision,$edit,$authorized))
  throw new RuntimeException('Active zone edit rejected');
if ($zones->update($zoneId,$buildingId,$revision,$edit,$authorized))
  throw new RuntimeException('Stale zone edit accepted');
$edited=$zones->forProjectZone('17',$zoneId,$projectAccess);
if (!is_array($edited) || (string)$edited['id']!==$zoneId
  || (int)$edited['revision']!==$revision+1
  || (float)$edited['radius_metres']!==110.0
  || !$membership->isActiveForProject('17',$zoneId))
  throw new RuntimeException('Edited zone lost its presence authorization');
$event['kind']='out';
if ($submit($token,'native_building_departure_0002',$event)['status']!==202) {
  throw new RuntimeException('Building-owned departure failed');
}
$event['project_id']='18';
if ($submit($token,'native_building_wrong_project_0003',$event)['status']!==422) {
  throw new RuntimeException('Unlinked project accepted');
}
$event['project_id']='17';
$event['employee_id']='999';
if ($submit($token,'native_building_spoofed_person_0004',$event)['status']!==400) {
  throw new RuntimeException('Client-supplied employee accepted');
}
unset($event['employee_id']);
$db->prepare('UPDATE office_building_zones SET active=0 WHERE id=?')->execute([$zoneId]);
if ($submit($token,'native_building_disabled_zone_0005',$event)['status']!==422) {
  throw new RuntimeException('Disabled building zone accepted');
}
$db->prepare('UPDATE office_building_zones SET active=1 WHERE id=?')->execute([$zoneId]);
$db->prepare('UPDATE office_onsite_device_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=?')
  ->execute([hash('sha256',$token)]);
if ($submit($token,'native_building_revoked_device_0006',$event)['status']!==401) {
  throw new RuntimeException('Revoked device accepted');
}
$after = (int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn();
if ($after-$before!==2) throw new RuntimeException('Unauthorized events persisted');
echo "Office-native building device-to-inbox integration OK\n";
