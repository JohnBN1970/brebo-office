<?php
declare(strict_types=1);
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');$stage=realpath($argv[1]??'');
if($base===false||$stage===false||!str_starts_with($stage,$base.'/.calc-acceptance-stage-'))exit(2);
chdir($base.'/config');$configFile=$base.'/config/config.php';$configHash=hash_file('sha256',$configFile);$config=require $configFile;
$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$lock=fopen($base.'/.calc-acceptance-install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(4);
$files=['OfficeCalcAcceptedLineAmounts','OfficeCalcWorkBudgetHandoff','OfficeCalcAcceptanceIdentity','OfficeCalcPersistedAcceptanceVerifier','OfficeCalcAcceptanceSnapshotWriter','OfficeCalcCrmTransferService'];
$tables=['office_crm_calc_acceptances','office_crm_calc_accepted_lines','office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines'];
$connect=static fn():PDO=>new PDO((string)$config['OFFICE_INTAKE_PDO_DSN'],(string)$config['OFFICE_INTAKE_DB_USER'],(string)$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$createdFiles=[];$phase='preflight';
try{
    foreach(['bcadd','bcmul','bccomp']as$f)if(!function_exists($f))throw new RuntimeException('bcmath_missing');
    $lint=static function(string$file):void{$pipes=[];$process=proc_open([PHP_BINARY,'-l',$file],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('syntax_process_failed');fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('syntax_check_failed');};
    foreach($files as$name){$path='/src/Calculation/'.$name.'.php';$lint($stage.$path);if(is_file($root.$path)&&!hash_equals((string)hash_file('sha256',$root.$path),(string)hash_file('sha256',$stage.$path)))throw new RuntimeException('unrecognized_deployed_service');}
    $lint($stage.'/probe.php');
    $db=$connect();if($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')throw new RuntimeException('isolated_test_database_required');
    $schemas=[];$types=[];
    foreach($tables as$table){$q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$cols=$q->fetchAll(PDO::FETCH_KEY_PAIR);$types[$table]=$cols;if($cols!==[]){if((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==0)throw new RuntimeException('nonempty_acceptance_tables_require_reconciliation');$schemas[$table]=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];}}
    foreach(['office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines']as$table)if(empty($types[$table]))throw new RuntimeException('work_budget_schema_missing');
    // Accept only the inspected integer baseline or our exact target, never narrow a different type.
    foreach(['office_crm_calc_accepted_lines','office_calc_work_budget_lines']as$table)foreach(['quantity'=>'decimal(24,4) unsigned','unit_cost_cents'=>'decimal(22,2) unsigned']as$column=>$expected){if(empty($types[$table]))continue;$actual=strtolower($types[$table][$column]??'');if(!in_array($actual,['bigint(20) unsigned','bigint unsigned',$expected],true))throw new RuntimeException('unrecognized_amount_column');}
    if(($types['office_crm_calc_acceptances']===[])!==($types['office_crm_calc_accepted_lines']===[]))throw new RuntimeException('partial_acceptance_schema');
    $stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));$backup=$base.'/.calc-acceptance-backups/'.$stamp;if(!is_dir(dirname($backup)))mkdir(dirname($backup),0700);if(!mkdir($backup,0700))throw new RuntimeException('backup_directory_failed');
    $backupBody=json_encode(['created_at'=>gmdate('c'),'schema'=>$schemas,'row_counts'=>'all_zero','config_sha256'=>$configHash],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(file_put_contents($backup.'/preflight.json',$backupBody,LOCK_EX)!==strlen($backupBody))throw new RuntimeException('backup_failed');chmod($backup.'/preflight.json',0600);
    $phase='schema';
    if($types['office_crm_calc_acceptances']===[])$db->exec((string)file_get_contents($stage.'/acceptance-schema.sql'));
    foreach(['office_crm_calc_accepted_lines','office_calc_work_budget_lines']as$table)$db->exec('ALTER TABLE `'.$table.'` MODIFY quantity DECIMAL(24,4) UNSIGNED NOT NULL, MODIFY unit_cost_cents DECIMAL(22,2) UNSIGNED NOT NULL');
    foreach(['office_crm_calc_accepted_lines','office_calc_work_budget_lines']as$table){$q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$cols=$q->fetchAll(PDO::FETCH_KEY_PAIR);if(strtolower($cols['quantity']??'')!=='decimal(24,4) unsigned'||strtolower($cols['unit_cost_cents']??'')!=='decimal(22,2) unsigned')throw new RuntimeException('decimal_schema_verification_failed');}
    $phase='files';$hashes=[];
    foreach($files as$name){$path='/src/Calculation/'.$name.'.php';if(!is_file($root.$path)){$tmp=tempnam(dirname($root.$path),'.acceptance-');if($tmp===false)throw new RuntimeException('temporary_file_failed');$body=(string)file_get_contents($stage.$path);if(file_put_contents($tmp,$body,LOCK_EX)!==strlen($body))throw new RuntimeException('write_failed');chmod($tmp,0600);if(!rename($tmp,$root.$path))throw new RuntimeException('atomic_swap_failed');$createdFiles[$root.$path]=hash('sha256',$body);}$hashes[$name]=hash_file('sha256',$root.$path);if(!hash_equals((string)$hashes[$name],(string)hash_file('sha256',$stage.$path)))throw new RuntimeException('service_hash_mismatch');if(function_exists('opcache_invalidate'))opcache_invalidate($root.$path,true);}
    $phase='probe';require $stage.'/probe.php';$probeDb=$connect();probeCalcAcceptance($probeDb,$root);$probeDb=null;
    foreach($tables as$table)if((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==0)throw new RuntimeException('business_table_count_changed');
    if(!hash_equals((string)$configHash,(string)hash_file('sha256',$configFile)))throw new RuntimeException('config_changed_during_install');
    $manifest=json_encode(['installed_at'=>gmdate('c'),'file_hashes'=>$hashes,'schema'=>'decimal_24_4_quantity_decimal_22_2_cents','probe'=>'session_local_pass','runtime_route'=>'not_added'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(file_put_contents($backup.'/installed.json',$manifest,LOCK_EX)!==strlen($manifest))throw new RuntimeException('manifest_write_failed');chmod($backup.'/installed.json',0600);
    echo "CALC_ACCEPTANCE_INSTALL=OFFICE_TEST_SERVICES_AND_SCHEMA_READY\nCALC_ACCEPTANCE_BUSINESS_ROWS=UNCHANGED_ZERO\nCALC_ACCEPTANCE_CONFIG=UNCHANGED\nCALC_ACCEPTANCE_RUNTIME_ROUTE=NOT_ADDED\n";
}catch(Throwable$e){foreach($createdFiles as$file=>$hash)if(is_file($file)&&hash_equals($hash,(string)hash_file('sha256',$file)))unlink($file);$reason=preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():get_class($e);if($e instanceof Error&&preg_match('/undefined function ([A-Za-z0-9_]+)\(/',$e->getMessage(),$missing))$reason='missing_function_'.$missing[1];echo 'CALC_ACCEPTANCE_INSTALL=FAILED_AT_'.$phase.'_'.$reason."\n";echo "CALC_ACCEPTANCE_SCHEMA_POLICY=ADDITIVE_DDL_RETAINED_FOR_SAFE_RETRY\n";exit(10);}finally{flock($lock,LOCK_UN);fclose($lock);}
