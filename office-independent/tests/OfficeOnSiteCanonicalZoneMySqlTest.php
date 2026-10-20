<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneSync.php';
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
$db->exec("UPDATE office_onsite_canonical_zones SET synchronized_at = UTC_TIMESTAMP() - INTERVAL 25 HOUR WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Expired zone still authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET synchronized_at = UTC_TIMESTAMP(), radius_metres = NULL WHERE zone_id = 8");
if ($check('17','8')) throw new RuntimeException('Zone without geometry still authorized');
$db->exec("UPDATE office_onsite_canonical_zones SET radius_metres = 125 WHERE zone_id = 8");
if (!$check('17','8')) throw new RuntimeException('Valid refreshed zone not authorized');
$sync = new \Brebo\Office\Inzet\OnSiteCanonicalZoneSync($db);
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>false,'source_revision'=>6]);
if ($check('17','8') || $check('18','8')) throw new RuntimeException('Newer inactive zone still authorized');
$sync->apply(['zone_id'=>'8','project_id'=>'17','active'=>true,'source_revision'=>5]);
if ($check('17','8')) throw new RuntimeException('Stale zone revision restored access');
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>true,'source_revision'=>7]);
if (!$check('18','8') || $check('17','8')) throw new RuntimeException('Project zone reassignment not enforced');
$conflictRejected = false;
try {
  $sync->apply(['zone_id'=>'8','project_id'=>'17','active'=>true,'source_revision'=>7]);
} catch (RuntimeException $exception) {
  $conflictRejected = $exception->getMessage() === 'Conflicting zone revision';
}
if (!$conflictRejected || !$check('18','8')) {
  throw new RuntimeException('Equal-revision conflicting zone was not rejected');
}
echo "Canonical Drupal zone MySQL sync and read projection OK\n";
