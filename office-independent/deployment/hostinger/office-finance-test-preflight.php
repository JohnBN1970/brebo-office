<?php

declare(strict_types=1);

// CLI-only metadata inspection. No database or runtime writes, no configuration values in output.
if (PHP_SAPI !== 'cli') exit(1);
set_exception_handler(static function (Throwable $error): never {
  fwrite(STDERR, 'FINANCE_PREFLIGHT_STOP | ' . get_class($error) . PHP_EOL);
  exit(1);
});
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';
$config=require $base.'/config/config.php';
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??'')!=='/office-test'
  || ($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'
  || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')) throw new RuntimeException('Isolation required');
$root=realpath($config['OFFICE_PRIVATE_ROOT']??'');
if (!is_string($root) || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private')) throw new RuntimeException('Private test root required');
echo 'FINANCE_TEST_ROOT | '.$root.PHP_EOL;
$db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$interesting=array_values(array_filter($tables,static fn(string $table):bool=>str_starts_with($table,'brebo_finance_')||str_starts_with($table,'office_finance_')||in_array($table,['office_projects','office_project_memberships','office_module_permissions','office_operator_accounts'],true)));
foreach ($interesting as $table) {
  if (!preg_match('/^[a-z0-9_]+$/D',$table)) throw new RuntimeException('Invalid table');
  echo 'FINANCE_TEST_TABLE | '.$table.' | '.$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn().PHP_EOL;
}
foreach (['office_projects','office_project_memberships','office_module_permissions','office_finance_project_mapping','office_operator_accounts','brebo_finance_purchase_invoice','brebo_finance_performance_receipt'] as $table) {
  $q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
  $q->execute([$table]);
  echo 'FINANCE_TEST_SCHEMA | '.$table.' | '.json_encode($q->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR).PHP_EOL;
}
echo 'FINANCE_TEST_ACCOUNT_IDS | '.json_encode($db->query('SELECT id,active FROM office_operator_accounts')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR).PHP_EOL;
echo 'FINANCE_TEST_PROJECT_IDS | '.json_encode($db->query('SELECT id,code FROM office_projects')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR).PHP_EOL;
if(in_array('office_onsite_project_mapping',$tables,true)) echo 'FINANCE_REUSABLE_PROJECT_MAPPING | '.json_encode($db->query('SELECT source_project_id,office_project_id,verified FROM office_onsite_project_mapping')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR).PHP_EOL;
$db->rollBack();
foreach (['OFFICE_FINANCE_ENABLED','OFFICE_FINANCE_SCHEMA_VERIFIED'] as $flag) echo 'FINANCE_FLAG | '.$flag.' | '.(($config[$flag]??'')==='enabled'?'enabled':'disabled').PHP_EOL;
foreach (['public/office-home.php','src/Intake/OfficeNavigation.php','modules/office-navigation/routes.php','modules/office-finance/routes.php'] as $relative) {
  $file=$root.'/'.$relative;
  echo 'FINANCE_RUNTIME_FILE | '.$relative.' | '.(is_file($file)?hash_file('sha256',$file):'missing').PHP_EOL;
  if (is_file($file) && str_ends_with($relative,'routes.php')) echo 'FINANCE_RUNTIME_ROUTES | '.$relative.' | '.base64_encode(file_get_contents($file)).PHP_EOL;
}
$router='/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
echo 'FINANCE_TEST_ROUTER | '.base64_encode(file_get_contents($router)).PHP_EOL;
echo 'FINANCE_TEST_REWRITE | '.base64_encode(file_get_contents(dirname($router).'/.htaccess')).PHP_EOL;
echo 'FINANCE_PREFLIGHT_READ_ONLY=PASS'.PHP_EOL;
// Inspect existing source metadata using a database-enforced read-only transaction.
$settingsFile='/home/u213420663/domains/brebobv.nl/public_html/sboffice/web/sites/default/settings.php';
if (is_file($settingsFile)) {
  try {
    $sourceConfig=(static function(string $file):?array {
      $databases=[]; $settings=[]; $config=[];
      $app_root=dirname($file,3); $site_path='sites/default';
      if (!defined('DRUPAL_ROOT')) define('DRUPAL_ROOT',$app_root);
      $autoload=dirname($app_root).'/vendor/autoload.php';
      if (is_file($autoload)) require_once $autoload;
      require $file;
      return $databases['default']['default']??null;
    })($settingsFile);
    if (!is_array($sourceConfig) || ($sourceConfig['driver']??'')!=='mysql') throw new RuntimeException('Source not configured');
    $sourceDsn='mysql:host='.($sourceConfig['host']??'localhost').';port='.($sourceConfig['port']??3306).';dbname='.$sourceConfig['database'].';charset=utf8mb4';
    $source=new PDO($sourceDsn,$sourceConfig['username'],$sourceConfig['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $source->exec('SET SESSION TRANSACTION READ ONLY'); $source->beginTransaction();
    $prefix=$sourceConfig['prefix']??'';
    if (!is_string($prefix) || !preg_match('/^[a-zA-Z0-9_]*$/D',$prefix)) throw new RuntimeException('Unsupported source prefix');
    foreach (['purchase_invoice','purchase_invoice_line','payment_release','commitment','commitment_line','performance_receipt','g_account_instruction'] as $name) {
      $table=$prefix.'brebo_finance_'.$name;
      try { echo 'FINANCE_SOURCE_TABLE | '.$name.' | '.$source->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn().PHP_EOL; }
      catch (PDOException) { echo 'FINANCE_SOURCE_TABLE | '.$name.' | unavailable'.PHP_EOL; }
    }
    $source->rollBack();
    echo 'FINANCE_SOURCE_READ_ONLY=PASS'.PHP_EOL;
  } catch (Throwable $error) { $message=$error->getMessage();
    $safe=preg_match('/(?:Undefined constant "[A-Z_]+"|Class "[A-Za-z_\\\\]+" not found|Call to undefined function [A-Za-z_\\\\]+\(\))/', $message, $match) ? $match[0] : 'details suppressed';
    echo 'FINANCE_SOURCE_METADATA_UNAVAILABLE | '.get_class($error).' | '.basename($error->getFile()).':'.$error->getLine().' | '.$safe.PHP_EOL; }
} else echo 'FINANCE_SOURCE_SETTINGS_UNAVAILABLE'.PHP_EOL;
