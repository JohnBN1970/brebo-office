<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeProjectMapSeed.php';
require_once dirname(__DIR__).'/src/Inzet/OnSiteOfficeProjectMapRepository.php';
use Brebo\Office\Inzet\OnSiteOfficeProjectMapRepository;
$dsn=getenv('OFFICE_INZET_TEST_DSN') ?: '';
if (!str_contains($dsn,'dbname=office_inzet_ci')) throw new RuntimeException('CI database required');
$db=new PDO($dsn,getenv('OFFICE_INZET_TEST_USER') ?: '',getenv('OFFICE_INZET_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TEMPORARY TABLE office_projects (id BIGINT PRIMARY KEY, latitude DECIMAL(10,7), longitude DECIMAL(10,7))');
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id BIGINT PRIMARY KEY, active TINYINT)');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (project_id BIGINT, operator_id BIGINT, permission VARCHAR(20))');
$db->exec("INSERT INTO office_projects VALUES (17,51.5,4.3),(18,52,5),(19,NULL,NULL)");
$db->exec("INSERT INTO office_operator_accounts VALUES (10,1),(11,0),(12,1)");
$db->exec("INSERT INTO office_project_memberships VALUES (17,10,'manage'),(18,10,'read'),(17,11,'manage'),(19,12,'manage')");
$debug=$db->query("SELECT p.id,p.latitude,p.longitude FROM office_projects p WHERE p.id=17")->fetch(PDO::FETCH_ASSOC);
if (!is_array($debug)) throw new RuntimeException('Expected test project missing');
foreach (['id','latitude','longitude'] as $field) {
  if (!array_key_exists($field,$debug)) throw new RuntimeException('Test project column missing: '.$field);
}
fwrite(STDOUT,'Map seed fixture types: '.get_debug_type($debug['id']).', '.get_debug_type($debug['latitude']).', '.get_debug_type($debug['longitude'])."\\n");
$validations=[
  'id_type'=>is_int($debug['id']) || is_string($debug['id']),
  'id_pattern'=>preg_match('/^[1-9][0-9]{0,18}$/D',(string)$debug['id'])===1,
  'lat_numeric'=>is_numeric($debug['latitude']),
  'lon_numeric'=>is_numeric($debug['longitude']),
  'lat_finite'=>is_finite((float)$debug['latitude']),
  'lon_finite'=>is_finite((float)$debug['longitude']),
  'lat_range'=>abs((float)$debug['latitude'])<=90,
  'lon_range'=>abs((float)$debug['longitude'])<=180,
];
foreach($validations as $check=>$passed) if(!$passed) throw new RuntimeException('Invalid map seed fixture check: '.$check);
$joined=$db->query("SELECT p.id,p.latitude,p.longitude FROM office_projects p INNER JOIN office_project_memberships m ON m.project_id=p.id INNER JOIN office_operator_accounts a ON a.id=m.operator_id WHERE p.id=17 AND m.operator_id=10 AND m.permission='manage' AND a.active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!is_array($joined)) throw new RuntimeException('Authorized joined project fixture missing');
if ($joined != $debug) throw new RuntimeException('Joined project differs from fixture');
\Brebo\Office\Inzet\OnSiteOfficeProjectMapSeed::fromAuthorizedProject($joined);
$repo=new OnSiteOfficeProjectMapRepository($db);
if ($repo->forManager(10,17)['project_id']!=='17') throw new RuntimeException('Authorized project missing');
foreach ([[10,18],[11,17],[12,17],[12,19]] as [$operator,$project]) {
  $denied=false;
  try {$repo->forManager($operator,$project);} catch (RuntimeException $e) {$denied=true;}
  if (!$denied) throw new RuntimeException('Project map access not denied');
}
echo "Office project map MySQL access checks passed\n";
