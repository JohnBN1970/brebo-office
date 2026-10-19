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
$db->exec("INSERT INTO office_onsite_canonical_zones(zone_id,project_id,active,source_revision)
  VALUES (8,17,1,5),(9,17,0,5),(10,18,1,2)");
$repo = new \Brebo\Office\Inzet\OnSiteCanonicalZoneRepository($db);
$check = $repo->callback();
if (!$check('17','8') || $check('17','9') || $check('17','10')
  || $check('18','8') || $check('17','11')) {
  throw new RuntimeException('Canonical zone projection authorized incorrectly');
}
$sync = new \\Brebo\\Office\\Inzet\\OnSiteCanonicalZoneSync($db);
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>false,'source_revision'=>6]);
if ($check('17','8') || $check('18','8')) throw new RuntimeException('Newer inactive zone still authorized');
$sync->apply(['zone_id'=>'8','project_id'=>'17','active'=>true,'source_revision'=>5]);
if ($check('17','8')) throw new RuntimeException('Stale zone revision restored access');
$sync->apply(['zone_id'=>'8','project_id'=>'18','active'=>true,'source_revision'=>7]);
if (!$check('18','8') || $check('17','8')) throw new RuntimeException('Project zone reassignment not enforced');
echo "Canonical Drupal zone MySQL sync and read projection OK\\n";
