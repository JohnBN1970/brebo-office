<?php
declare(strict_types=1);
// Read-only release gate. Never changes config, flags, database or calculation data.
if(PHP_SAPI!=='cli')exit(2);
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$config=$base&&is_file($base.'/config/config.php')?require $base.'/config/config.php':null;
$root=is_array($config)?realpath((string)($config['OFFICE_PRIVATE_ROOT']??'')):false;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!$root||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')){
 echo "ACTIVATION_GATE=BLOCKED\nREASON=OFFICE_TEST_CONFIG\n";exit(2);
}
$paths=['public/office-calc-crm-workbench.php','src/Calculation/OfficeCalcCrmDraftLineRepository.php','src/Calculation/OfficeCalcQuantityAdjustments.php'];
foreach($paths as $path){
 if(!is_file($root.'/'.$path)||is_link($root.'/'.$path)){echo "ACTIVATION_GATE=BLOCKED\nREASON=FILES\n";exit(2);}
}
$dsn=(string)($config['OFFICE_INTAKE_PDO_DSN']??getenv('OFFICE_INTAKE_PDO_DSN'));
$user=(string)($config['OFFICE_INTAKE_DB_USER']??getenv('OFFICE_INTAKE_DB_USER'));
$pass=(string)($config['OFFICE_INTAKE_DB_PASSWORD']??getenv('OFFICE_INTAKE_DB_PASSWORD'));
try{
 $db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $expected=['calc_net_quantity'=>'decimal(10,4)','calc_material_loss_percent'=>'decimal(5,2)','calc_extra_labor_hours'=>'decimal(10,4)','calc_quantity_source'=>'varchar(64)','calc_quantity_source_ref'=>'varchar(128)'];
 $q=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'");
 $cols=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$cols[(string)$row['COLUMN_NAME']]=$row;
 foreach($expected as $name=>$type)if(!isset($cols[$name])||strtolower((string)$cols[$name]['COLUMN_TYPE'])!==$type||(string)$cols[$name]['IS_NULLABLE']!=='YES')throw new RuntimeException('SCHEMA');
 $flag=(string)($config['OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED']??getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED'));
 echo "ACTIVATION_GATE=PASS\nSCHEMA=PASS\nFILES=3\nFLAG_CONFIGURED=".($flag==='enabled'?'YES':'NO')."\n";
}catch(Throwable $e){echo "ACTIVATION_GATE=BLOCKED\nREASON=DATABASE_OR_SCHEMA\n";exit(2);}
