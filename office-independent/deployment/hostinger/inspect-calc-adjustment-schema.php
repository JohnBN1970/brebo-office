<?php
declare(strict_types=1);
// Read-only CLI schema inspection. Credentials must come from the existing Office
// runtime environment. No configuration files or secrets are printed.
if(PHP_SAPI!=='cli')exit(2);
$dsn=(string)getenv('OFFICE_INTAKE_PDO_DSN');
$user=(string)getenv('OFFICE_INTAKE_DB_USER');
$pass=(string)getenv('OFFICE_INTAKE_DB_PASSWORD');
if($dsn===''||$user===''){fwrite(STDERR,"SCHEMA_CHECK=ENV_NOT_LOADED\n");exit(2);}
try{
 $db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('driver');
 $expected=[
  'calc_net_quantity'=>'decimal(10,4)',
  'calc_material_loss_percent'=>'decimal(5,2)',
  'calc_extra_labor_hours'=>'decimal(10,4)',
  'calc_quantity_source'=>'varchar(64)',
  'calc_quantity_source_ref'=>'varchar(128)',
 ];
 $stmt=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'");
 $actual=[];
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$actual[(string)$row['COLUMN_NAME']]=$row;
 if(!isset($actual['id'])){echo "SCHEMA_CHECK=TABLE_MISSING\n";exit(1);}
 $missing=[];$invalid=[];
 foreach($expected as $name=>$type){
  if(!isset($actual[$name])){$missing[]=$name;continue;}
  if(strtolower((string)$actual[$name]['COLUMN_TYPE'])!==$type||(string)$actual[$name]['IS_NULLABLE']!=='YES')$invalid[]=$name;
 }
 echo "SCHEMA_CHECK=".($invalid?'BLOCKED':($missing?'MIGRATION_REQUIRED':'PASS'))."\n";
 echo "COLUMNS_PRESENT=".(count($expected)-count($missing))."\n";
 echo "COLUMNS_MISSING=".implode(',',$missing)."\n";
 echo "COLUMNS_INVALID=".implode(',',$invalid)."\n";
 exit($invalid?2:($missing?1:0));
}catch(Throwable $e){fwrite(STDERR,"SCHEMA_CHECK=ERROR\n");exit(2);}
