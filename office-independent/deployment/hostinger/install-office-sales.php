<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
require dirname(__DIR__,2).'/src/Sales/OfficeSalesSchema.php';
try {
  if (($argv[1]??'')!=='--apply-office-test-sales') throw new RuntimeException('Explicit installation required');
  $path=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test/config/config.php';
  if (is_link($path) || realpath($path)!==$path) throw new RuntimeException('Unsafe configuration');
  $config=require $path;
  if (!is_array($config) || ($config['OFFICE_BASE_PATH']??'')!=='/office-test'
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')
      || ($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'
      || ($config['OFFICE_SALES_ENABLED']??'disabled')==='enabled') throw new RuntimeException('Sales must be disabled in isolated Office-test');
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $locked=(int)$db->query("SELECT GET_LOCK('office-native-sales-install',0)")->fetchColumn();
  if ($locked!==1) throw new RuntimeException('Another installation is running');
  $tables=['office_finance_sales_invoice','office_finance_sales_delivery','office_finance_sales_audit'];
  foreach ($tables as $table) {
    $q=$db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
    if ((int)$q->fetchColumn()!==0) throw new RuntimeException('Existing sales schema requires reconciliation; nothing overwritten');
  }
  foreach (\Brebo\Office\Sales\OfficeSalesSchema::statements() as $sql) $db->exec($sql);
  $db->query("SELECT RELEASE_LOCK('office-native-sales-install')");
  echo "OFFICE_SALES_ADDITIVE_SCHEMA_INSTALLED=1\nOFFICE_SALES_NOT_ACTIVATED=1\n";
} catch (Throwable $e) { echo "OFFICE_SALES_INSTALL_BLOCKED=1\n"; exit(1); }
