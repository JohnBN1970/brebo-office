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
$repo=new OnSiteOfficeProjectMapRepository($db);
if ($repo->forManager(10,17)['project_id']!=='17') throw new RuntimeException('Authorized project missing');
foreach ([[10,18],[11,17],[12,17],[12,19]] as [$operator,$project]) {
  $denied=false;
  try {$repo->forManager($operator,$project);} catch (RuntimeException $e) {$denied=true;}
  if (!$denied) throw new RuntimeException('Project map access not denied');
}
echo "Office project map MySQL access checks passed\n";
