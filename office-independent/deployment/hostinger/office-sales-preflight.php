<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
try {
  $base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';
  $path=$base.'/config/config.php';
  if (is_link($path) || realpath($path)!==$path) throw new RuntimeException('Unsafe test configuration');
  $hash=hash_file('sha256',$path); $config=require $path;
  $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if (!is_array($config) || ($config['OFFICE_BASE_PATH']??'')!=='/office-test' || !is_string($root) || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'') || ($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test') throw new RuntimeException('Wrong test scope');
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db->exec('SET SESSION TRANSACTION READ ONLY'); $db->beginTransaction();
  foreach (['office_finance_sales_invoice','office_finance_sales_delivery','office_finance_sales_audit','office_finance_sales_receipt','office_crm_relations','office_crm_external_identities'] as $table) {
    $q=$db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'); $q->execute([$table]);
    echo 'SALES_TABLE_'.$table.'_PRESENT='.(int)((int)$q->fetchColumn()>0)."\n";
  }
  foreach (['read','draft_edit'] as $permission) {
    $q=$db->prepare("SELECT 1 FROM office_module_permissions p JOIN office_operator_accounts a ON a.id=p.operator_id WHERE a.id=? AND a.active=1 AND p.module='finance' AND p.permission=?");$q->execute(['1',$permission]);
    echo 'SALES_EXISTING_OPERATOR_'.$permission.'='.(int)($q->fetchColumn()!==false)."\n";
  }
  foreach (['OFFICE_SALES_ENABLED','OFFICE_SALES_SCHEMA_VERIFIED','OFFICE_SALES_SEND_ENABLED','OFFICE_SALES_REMINDERS_ENABLED'] as $key) echo $key.'_CURRENT='.(($config[$key]??'disabled')==='enabled'?'enabled':'disabled')."\n";
  $tax=json_decode($config['OFFICE_SALES_MONEYBIRD_TAX_RATES']??'{}',true);
  foreach (['0','9','21'] as $rate) echo 'SALES_TAX_MAPPING_'.$rate.'_PRESENT='.(int)(is_array($tax)&&is_string($tax[$rate]??null)&&preg_match('/^\d+$/D',$tax[$rate]))."\n";
  $q=$db->prepare("SELECT 1 FROM office_crm_external_identities e JOIN office_crm_relations r ON r.id=e.relation_id WHERE e.provider_code='moneybird' AND r.status='active' LIMIT 1"); $q->execute();
  echo 'SALES_CANONICAL_MONEYBIRD_MAPPING_PRESENT='.(int)($q->fetchColumn()!==false)."\n";
  foreach (['src/Sales/OfficeSalesService.php','public/office-sales.php'] as $file) echo 'SALES_FILE_'.str_replace(['/','.'],['_','_'],$file).'_PRESENT='.(int)is_file($root.'/'.$file)."\n";
  $router=dirname($base).'/public_html/office-test/index.php';
  if (is_file($router) && !is_link($router)) echo 'SALES_ROUTE_PRESENT='.(int)str_contains(file_get_contents($router),"'/office-test/finance/verkoop'")."\n";
  if (is_file($router) && !is_link($router)) {
    foreach (file($router,FILE_IGNORE_NEW_LINES) as $line) {
      if (preg_match('/finance|require|include|router|manifest|moduleRoutes|glob\(|\$routes/i',$line) && strlen($line)<1000) echo 'SALES_FINANCE_ROUTER_SOURCE='.trim($line)."\n";
    }
  }
  $db->rollBack();
  if (hash_file('sha256',$path)!==$hash) throw new RuntimeException('Configuration changed');
  echo "SALES_PREFLIGHT_READ_ONLY_OK=1\n";
} catch (Throwable $e) { echo "SALES_PREFLIGHT_BLOCKED=1\n"; exit(1); }
