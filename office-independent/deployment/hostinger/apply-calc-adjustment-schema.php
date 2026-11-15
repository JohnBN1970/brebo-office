<?php
declare(strict_types=1);
// Explicit CLI-only, Office-test-only schema migration. No feature flags changed.
// Usage: OFFICE_INTAKE_PDO_DSN=... OFFICE_INTAKE_DB_USER=... OFFICE_INTAKE_DB_PASSWORD=... php apply-calc-adjustment-schema.php --apply
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply'){fwrite(STDERR,"MIGRATION=EXPLICIT_APPLY_REQUIRED\n");exit(2);}
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$config=$base&&is_file($base.'/config/config.php')?require $base.'/config/config.php':null;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'){
 fwrite(STDERR,"MIGRATION=OFFICE_TEST_CONFIG_REQUIRED\\n");exit(2);
}
$dsn=(string)($config['OFFICE_INTAKE_PDO_DSN']??getenv('OFFICE_INTAKE_PDO_DSN'));
$user=(string)($config['OFFICE_INTAKE_DB_USER']??getenv('OFFICE_INTAKE_DB_USER'));
$pass=(string)($config['OFFICE_INTAKE_DB_PASSWORD']??getenv('OFFICE_INTAKE_DB_PASSWORD'));
if($dsn===''||$user===''){fwrite(STDERR,"MIGRATION=ENV_NOT_LOADED\n");exit(2);}
$columns=[
 'calc_net_quantity'=>'DECIMAL(10,4) NULL',
 'calc_material_loss_percent'=>'DECIMAL(5,2) NULL',
 'calc_extra_labor_hours'=>'DECIMAL(10,4) NULL',
 'calc_quantity_source'=>'VARCHAR(64) NULL',
 'calc_quantity_source_ref'=>'VARCHAR(128) NULL',
];
$lock=false;
try{
 $db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('DRIVER');
 $database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
 if($database==='')throw new RuntimeException('DATABASE');
 $q=$db->query("SELECT GET_LOCK('brebo_calc_adjustment_schema_20261114',10)");
 $lock=(int)$q->fetchColumn()===1;
 if(!$lock)throw new RuntimeException('LOCK');
 $read=static function()use($db):array{
  $rows=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'")->fetchAll(PDO::FETCH_ASSOC);
  $out=[];foreach($rows as $row)$out[(string)$row['COLUMN_NAME']]=$row;
  return $out;
 };
 $existing=$read();
 if(!isset($existing['id']))throw new RuntimeException('TABLE_MISSING');
 foreach($columns as $name=>$definition){
  if(isset($existing[$name])){
   $type=strtolower(explode(' ',$definition)[0]);
   if(strtolower((string)$existing[$name]['COLUMN_TYPE'])!==$type||(string)$existing[$name]['IS_NULLABLE']!=='YES')throw new RuntimeException('COLUMN_CONFLICT_'.$name);
  }
 }
 $before=(int)$db->query('SELECT COUNT(*) FROM calculation_lines')->fetchColumn();
 $added=0;
 foreach($columns as $name=>$definition){
  if(isset($existing[$name]))continue;
  $db->exec('ALTER TABLE calculation_lines ADD COLUMN `'.$name.'` '.$definition);
  $added++;
 }
 $after=$read();
 foreach($columns as $name=>$definition){
  $type=strtolower(explode(' ',$definition)[0]);
  if(!isset($after[$name])||strtolower((string)$after[$name]['COLUMN_TYPE'])!==$type||(string)$after[$name]['IS_NULLABLE']!=='YES')throw new RuntimeException('VERIFY_'.$name);
 }
 $count=(int)$db->query('SELECT COUNT(*) FROM calculation_lines')->fetchColumn();
 if($count!==$before)throw new RuntimeException('ROW_COUNT_CHANGED');
 echo "MIGRATION=PASS\nCOLUMNS_ADDED=$added\nCOLUMNS_VERIFIED=5\nROW_COUNT_UNCHANGED=YES\nFEATURE_FLAGS=UNCHANGED\n";
}catch(Throwable $e){fwrite(STDERR,"MIGRATION=BLOCKED\nREASON=".preg_replace('/[^A-Z0-9_]/','',strtoupper($e->getMessage()))."\n");exit(1);}
finally{if($lock)$db->query("SELECT RELEASE_LOCK('brebo_calc_adjustment_schema_20261114')");}
