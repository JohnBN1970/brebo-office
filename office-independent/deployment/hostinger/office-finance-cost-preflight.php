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
  $sources=['office'=>$db];
  if(is_string($config['OFFICE_FINANCE_READ_PDO_DSN']??null)&&$config['OFFICE_FINANCE_READ_PDO_DSN']!==''){
    if(!str_starts_with($config['OFFICE_FINANCE_READ_PDO_DSN'],'mysql:'))throw new RuntimeException('Invalid read source');
    $source=new PDO($config['OFFICE_FINANCE_READ_PDO_DSN'],$config['OFFICE_FINANCE_READ_DB_USER']??'',$config['OFFICE_FINANCE_READ_DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $source->exec('SET SESSION TRANSACTION READ ONLY');$source->beginTransaction();$sources['finance_read']=$source;
  }
  foreach($sources as $label=>$connection){
    $q=$connection->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME");
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $table){
      if(!preg_match('/^[A-Za-z0-9_]+$/D',$table)||!preg_match('/(?:office|brebo_finance).*?(?:invoice|cost|labou?r|budget|cash|balance|ledger|journal|payment|rate|hours|receipt|account_role|finance_project_mapping)/i',$table))continue;
      $q=$connection->prepare('SELECT COLUMN_NAME,DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$fields=$q->fetchAll(PDO::FETCH_ASSOC);$names=array_column($fields,'COLUMN_NAME');
      echo 'FINANCE_COST_SOURCE_'.$label.'_'.$table.'_FIELDS='.implode(',',array_map(static fn(array $f):string=>$f['COLUMN_NAME'].':'.$f['DATA_TYPE'],$fields))."\n";
      echo 'FINANCE_COST_SOURCE_'.$label.'_'.$table.'_NONEMPTY='.(int)($connection->query('SELECT 1 FROM `'.$table.'` LIMIT 1')->fetchColumn()!==false)."\n";
      if(in_array('status',$names,true)){$rows=$connection->query('SELECT DISTINCT status FROM `'.$table.'` LIMIT 20')->fetchAll(PDO::FETCH_COLUMN);$statuses=array_filter($rows,static fn($v):bool=>is_string($v)&&preg_match('/^[a-z_]{1,40}$/D',$v));echo 'FINANCE_COST_SOURCE_'.$label.'_'.$table.'_STATUSES='.implode(',',$statuses)."\n";}
    }
    $connection->rollBack();
  }
  foreach(['src/Finance/OfficeFinanceSalesOverview.php','src/Finance/OfficeFinanceDraftRegister.php','src/Inzet/OfficeInzetHoursOverview.php','src/Finance/OfficeMoneybirdBankSnapshot.php'] as $file){$f=$root.'/'.$file;echo 'FINANCE_COST_FILE_'.str_replace(['/','.'],['_','_'],$file).'='.(int)(is_file($f)&&!is_link($f))."\n";}
  if(hash_file('sha256',$path)!==$hash)throw new RuntimeException('Config changed');
  echo "FINANCE_COST_PREFLIGHT_READ_ONLY_OK=1\nFINANCE_COST_PREFLIGHT_NO_PROVIDER_CALLS=1\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();echo "FINANCE_COST_PREFLIGHT_FAILED=1\n";exit(1);}
