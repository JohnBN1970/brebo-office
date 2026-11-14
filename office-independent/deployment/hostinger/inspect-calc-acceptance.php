<?php
declare(strict_types=1);
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
if($base===false)exit(2);
chdir($base.'/config');
$config=require $base.'/config/config.php';
$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
foreach(glob($root.'/src/Calculation/*.php')?:[] as$file)echo 'ACCEPTANCE_FILE='.basename($file).' SHA256='.hash_file('sha256',$file)."\n";
foreach(array_merge(glob($root.'/public/*.php')?:[],glob($root.'/src/Crm/*.php')?:[])as$file){
 $body=(string)file_get_contents($file);
 if(preg_match('/OfficeCalcAcceptanceSnapshotWriter|OfficeCalcCrmTransferService|office_crm_calc_accepted_lines|OfficeCalcWorkBudgetHandoff/',$body)){
  echo 'ACCEPTANCE_RUNTIME='.basename($file).' SHA256='.hash_file('sha256',$file)."\n";
  foreach(explode("\n",$body)as$i=>$line)if(preg_match('/OfficeCalcAcceptanceSnapshotWriter|OfficeCalcCrmTransferService|unit_cost_cents|quantity_decimal|unit_cost_decimal|accepted_lines|require_once/',$line))echo 'RUNTIME_REFERENCE='.basename($file).':'.($i+1).' '.substr(trim($line),0,600)."\n";
 }
}
$db=new PDO((string)$config['OFFICE_INTAKE_PDO_DSN'],(string)$config['OFFICE_INTAKE_DB_USER'],(string)$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')exit(4);
$db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
foreach(['office_crm_calc_acceptances','office_crm_calc_accepted_lines','office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines']as$table){
 $q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$cols=$q->fetchAll(PDO::FETCH_ASSOC);
 echo 'ACCEPTANCE_COLUMNS.'.$table.'='.json_encode($cols,JSON_THROW_ON_ERROR)."\n";
 if($cols!==[])echo 'ACCEPTANCE_COUNT.'.$table.'='.$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()."\n";
}
$db->rollBack();echo "CALC_ACCEPTANCE_INSPECTION=READ_ONLY_OK\n";
