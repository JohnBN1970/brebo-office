<?php
declare(strict_types=1);
// CLI-only read-only inspection. Never prints configuration values or row data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';
$config=require $base.'/config/config.php';
$root=realpath($argv[1]??'');
$ok=true;
$check=static function(string $name,bool $pass) use (&$ok): void {
  echo ($pass?'PASS':'BLOCK').' | '.$name.PHP_EOL; $ok=$ok&&$pass;
};
$check('isolated active private root',is_string($root) && str_starts_with($root,$base.'/') && str_ends_with($root,'/private') && ($config['OFFICE_PRIVATE_ROOT']??'')===$root);
$check('isolated mount',($config['OFFICE_BASE_PATH']??'')==='/office-test');
$check('isolated test database',preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')===1 && ($config['OFFICE_INTAKE_DB_USER']??'')==='u213420663_office_test');
$check('Calc launch disabled',($config['OFFICE_CALC_LAUNCH_ENABLED']??'')!=='enabled');
$isolated=$ok;
if (!$isolated) exit(1);
foreach (['OFFICE_NATIVE_PROJECTS_ENABLED','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED',
  'OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED','OFFICE_TEST_ZONE_WRITE_ENABLED',
  'OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED','OFFICE_CRM_SESSION_MODE'] as $flag) {
  $check($flag,($config[$flag]??'')==='enabled');
}
$check('zone signing secret configured',strlen($config['OFFICE_TEST_ZONE_CSRF_SECRET']??'')>=32);
$router=file_get_contents('/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php');
foreach (['/office-test/inzet/projectkaart','/office-test/inzet/zone/aanmaken','/office-test/inzet/zone/bijwerken'] as $route) $check('router '.$route,is_string($router)&&str_contains($router,$route));
$check('router selects active root',is_string($router)&&(str_contains($router,(string)$root)||str_contains($router,"getenv('OFFICE_PRIVATE_ROOT')")));
$class=$root.'/src/Buildings/OfficeBuildingZoneReadiness.php';
$check('schema inspector present',is_file($class));
if (!is_file($class)) exit(1);
require $class;
try {
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD']??'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $db->exec('SET TRANSACTION READ ONLY');
  $db->beginTransaction();
  foreach (\Brebo\Office\Buildings\OfficeBuildingZoneReadiness::inspect($db) as $label=>$pass) $check($label,$pass);
  $location=$db->query("SELECT 1 FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE a.active=1 AND m.permission='manage' AND p.latitude BETWEEN -90 AND 90 AND p.longitude BETWEEN -180 AND 180 LIMIT 1");
  $check('manageable test project has valid coordinates',$location->fetchColumn()!==false);
  $db->rollBack();
} catch (Throwable) {
  if (isset($db)&&$db->inTransaction()) $db->rollBack();
  $check('read-only zone schema inspection',false);
}
foreach (['public/office-onsite-project-map.php','src/Inzet/OnSiteOfficeCircleCreateService.php','src/Buildings/OfficeBuildingZoneRepository.php'] as $path) {
  $source=file_get_contents($root.'/'.$path);
  foreach (['/office-test/inzet/zone/bewerken','Math.round(zone.radius/10)','->createOnce(','->create($input'] as $needle) {
    echo 'SOURCE | '.$path.' | '.$needle.' | '.(str_contains($source,$needle)?'present':'absent').PHP_EOL;
  }
}
exit($ok?0:1);

