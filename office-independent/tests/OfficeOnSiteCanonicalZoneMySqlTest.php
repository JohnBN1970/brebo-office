<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneSync.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneImport.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteVerifiedProjectMapping.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeZoneWriter.php';
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn,'dbname=office_inzet_ci')) throw new RuntimeException('Dedicated MySQL CI database required');
$db = new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);
foreach (\Brebo\Office\Inzet\OnSiteCanonicalZoneSchema::statements() as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_onsite_canonical_zones(zone_id,project_id,active,source_revision,zone_name,latitude,longitude,radius_metres)
  VALUES (8,17,1,5,'Werkzone',51.5000000,4.3000000,125),(9,17,0,5,'Inactief',51.5000000,4.3000000,125),(10,18,1,2,'Ander project',51.5000000,4.3000000,125)");
$repo = new \Brebo\Office\Inzet\OnSiteCanonicalZoneRepository($db);
$check = $repo->callback();
if (!$check('17','8') || $check('17','9') || $check('17','10')
  || $check('18','8') || $check('17','11')) {
  $details = $db->query("SELECT zone_id, project_id, active, zone_name, synchronized_at, latitude, longitude, radius_metres, UTC_TIMESTAMP() AS now_utc FROM office_onsite_canonical_zones ORDER BY zone_id")->fetchAll(PDO::FETCH_ASSOC);
  throw new RuntimeException('Canonical zone projection authorized incorrectly: ' . json_encode(['valid'=>$check('17','8'),'inactive'=>$check('17','9'),'other_zone'=>$check('17','10'),'wrong_project'=>$check('18','8'),'missing'=>$check('17','11'),'rows'=>$details]));
}
$db->exec("UPDATE office_onsite_canonical_zones SET zone_name = '   ' WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Whitespace-only zone name authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET zone_name = 'Werkzone' WHERE zone_id = 8");
if (!$check('17','8')) throw new RuntimeException('Restored zone name not authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET synchronized_at = UTC_TIMESTAMP() - INTERVAL 25 HOUR WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Expired zone still authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET synchronized_at = UTC_TIMESTAMP(), radius_metres = NULL WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Zone without geometry still authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET radius_metres = 125 WHERE zone_id = 8");
if (!$check('17','8')) throw new RuntimeException('Valid refreshed zone not authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET radius_metres = 5000 WHERE zone_id = 8");
if (!$check('17','8')) throw new RuntimeException('Existing 5000m project zone wrongly rejected');
$db->exec("UPDATE office_onsite_canonical_zones SET radius_metres = 5001 WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Zone radius above 5000m authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET radius_metres = 125 WHERE zone_id = 8");
$sync = new \Brebo\Office\Inzet\OnSiteCanonicalZoneSync($db);
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>false,'source_revision'=>6]);
if ($check('17','8') || $check('18','8')) throw new RuntimeException('Newer inactive zone still authorized');
$sync->apply(['zone_id'=>'8','project_id'=>'17','active'=>true,'source_revision'=>5,'zone_name'=>'Werkzone','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125]);
if ($check('17','8')) throw new RuntimeException('Stale zone revision restored access');
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>true,'source_revision'=>7,'zone_name'=>'Nieuwe zone','latitude'=>51.6,'longitude'=>4.4,'radius_metres'=>150]);
if (!$check('18','8') || $check('17','8')) throw new RuntimeException('Project zone reassignment not enforced');
$geometry = $db->query("SELECT zone_name, latitude, longitude, radius_metres FROM office_onsite_canonical_zones WHERE zone_id = 8")->fetch(PDO::FETCH_ASSOC);
if ($geometry['zone_name'] !== 'Nieuwe zone' || abs((float)$geometry['latitude'] - 51.6) > 0.000001 || abs((float)$geometry['radius_metres'] - 150) > 0.01) throw new RuntimeException('New revision geometry not synchronized');
$invalidGeometryRejected = false;
try {
  $sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>true,'source_revision'=>8,'zone_name'=>'','latitude'=>51.6,'longitude'=>4.4,'radius_metres'=>150]);
} catch (InvalidArgumentException $exception) {
  $invalidGeometryRejected = $exception->getMessage() === 'invalid_zone_geometry';
}
if (!$invalidGeometryRejected || !$check('18','8')) throw new RuntimeException('Invalid geometry changed active zone');
$conflictRejected = false;
try {
  $sync->apply(['zone_id'=>'8','project_id'=>'17','active'=>true,'source_revision'=>7,'zone_name'=>'Werkzone','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125]);
} catch (RuntimeException $exception) {
  $conflictRejected = $exception->getMessage() === 'Conflicting zone revision';
}
if (!$conflictRejected || !$check('18','8')) {
  throw new RuntimeException('Equal-revision conflicting zone was not rejected');
}
$geometryConflictRejected = false;
try {
  $sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>true,'source_revision'=>7,'zone_name'=>'Nieuwe zone','latitude'=>51.7,'longitude'=>4.4,'radius_metres'=>150]);
} catch (RuntimeException $exception) {
  $geometryConflictRejected = $exception->getMessage() === 'Conflicting zone revision';
}
if (!$geometryConflictRejected || !$check('18','8')) throw new RuntimeException('Equal-revision conflicting geometry not rejected');
$db->exec(\Brebo\Office\Inzet\OnSiteVerifiedProjectMapping::schema());
$db->exec("INSERT INTO office_onsite_project_mapping(source_project_id,office_project_id,verified) VALUES (77,17,1),(78,18,0)");
$mapping = new \Brebo\Office\Inzet\OnSiteVerifiedProjectMapping($db);
$import = new \Brebo\Office\Inzet\OnSiteCanonicalZoneImport($sync, $mapping->resolver());
$unverifiedRejected = false;
try {
  $import->import(['zone_id'=>21,'source_project_id'=>78,'source_revision'=>1,'active'=>true,'zone_name'=>'Import','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125 ]);
} catch (InvalidArgumentException $exception) {
  $unverifiedRejected = $exception->getMessage() === 'unverified_office_project';
}
if (!$unverifiedRejected) throw new RuntimeException('Zone import accepted unverified Office project');
$import->import(['zone_id'=>21,'source_project_id'=>77,'source_revision'=>1,'active'=>true,'zone_name'=>'Import','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125]);
if (!$check('17','21')) throw new RuntimeException('Verified zone import failed');
$writer = new \Brebo\Office\Inzet\OnSiteOfficeZoneWriter($db, $sync, static fn (string $id): bool => $id === '17');
$officeZone = ['zone_id'=>'21','project_id'=>'17','expected_revision'=>1,'active'=>true,
  'zone_name'=>'Office zone','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125];
if (!$writer->save($officeZone)) throw new RuntimeException('Office zone update failed');
$officeZone['zone_name'] = 'Stale change';
$staleRejected = false;
try {
  $writer->save($officeZone);
} catch (RuntimeException $exception) {
  $staleRejected = $exception->getMessage() === 'stale_office_zone_revision';
}
if (!$staleRejected || $db->inTransaction()) throw new RuntimeException('Stale Office edit was not rolled back');
$officeZone['project_id'] = '18';
$officeZone['expected_revision'] = 2;
$unauthorizedRejected = false;
try {
  $writer->save($officeZone);
} catch (InvalidArgumentException $exception) {
  $unauthorizedRejected = $exception->getMessage() === 'unauthorized_office_project';
}
if (!$unauthorizedRejected || !$check('17','21')) throw new RuntimeException('Unauthorized Office edit accepted');
$crossProjectWriter = new \Brebo\Office\Inzet\OnSiteOfficeZoneWriter($db, $sync, static fn (string $id): bool => in_array($id, ['17','18'], true));
$crossProjectRejected = false;
try {
  $crossProjectWriter->save(['zone_id'=>'21','project_id'=>'18','expected_revision'=>2,
    'active'=>true,'zone_name'=>'Hijacked','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125]);
} catch (InvalidArgumentException $exception) {
  $crossProjectRejected = $exception->getMessage() === 'office_zone_project_mismatch';
}
if (!$crossProjectRejected || $db->inTransaction() || !$check('17','21') || $check('18','21')) {
  throw new RuntimeException('Office zone moved across authorized projects');
}
$newZoneRejected = false;
try {
  $writer->save(['zone_id'=>'999999','project_id'=>'17','expected_revision'=>0,
    'active'=>true,'zone_name'=>'Unallocated zone','latitude'=>51.5,'longitude'=>4.3,'radius_metres'=>125]);
} catch (RuntimeException $exception) {
  $newZoneRejected = $exception->getMessage() === 'office_zone_create_not_supported';
}
if (!$newZoneRejected || $db->inTransaction() || $check('17','999999')) {
  throw new RuntimeException('Unallocated Office zone was not rejected safely');
}

echo "Canonical zone MySQL sync, Office edits and read projection OK\n";
