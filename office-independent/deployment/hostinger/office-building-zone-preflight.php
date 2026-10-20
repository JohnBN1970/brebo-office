<?php
declare(strict_types=1);
/** Run from the isolated test release. Outputs only schema labels, never row data. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$configFile='/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';
try {
  $config=is_file($configFile) ? require $configFile : null;
  if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? '')!=='/office-test'
    || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN'] ?? '')
    || ($config['OFFICE_INTAKE_DB_USER'] ?? '')!=='u213420663_office_test') {
    throw new RuntimeException('isolated_test_config_required');
  }
  require_once dirname(__DIR__,2).'/src/Buildings/OfficeBuildingZoneReadiness.php';
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $report=\Brebo\Office\Buildings\OfficeBuildingZoneReadiness::inspect($db);
  foreach ($report as $label=>$ok) echo ($ok?'PASS ':'BLOCK ').$label.PHP_EOL;
  exit(in_array(false,$report,true)?1:0);
} catch (Throwable) {
  echo "BLOCK isolated test schema inspection unavailable\n";exit(1);
}

