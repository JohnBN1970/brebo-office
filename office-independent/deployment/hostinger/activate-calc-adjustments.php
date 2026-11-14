<?php
declare(strict_types=1);
// Office-test only, explicit CLI activation with backup and atomic config replacement.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--enable'){echo "ACTIVATE=EXPLICIT_ENABLE_REQUIRED\n";exit(2);}
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$file=$base?$base.'/config/config.php':'';
if(!$base||!is_file($file)||is_link($file)){echo "ACTIVATE=BLOCKED_CONFIG\n";exit(2);}
$config=require $file;
$root=is_array($config)?realpath((string)($config['OFFICE_PRIVATE_ROOT']??'')):false;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!$root||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')){echo "ACTIVATE=BLOCKED_SCOPE\n";exit(2);}
foreach($config as $key=>$value)if(!is_string($key)||!is_string($value))exit(2);
$dsn=(string)($config['OFFICE_INTAKE_PDO_DSN']??'');
if(!preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'){echo "ACTIVATE=BLOCKED_DATABASE_SCOPE\n";exit(2);}
try{
 $db=new PDO($dsn,$config['OFFICE_INTAKE_DB_USER'],(string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $rows=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'")->fetchAll(PDO::FETCH_ASSOC);
 $actual=[];foreach($rows as $row)$actual[$row['COLUMN_NAME']]=$row;
 foreach(['calc_net_quantity'=>'decimal(10,4)','calc_material_loss_percent'=>'decimal(5,2)','calc_extra_labor_hours'=>'decimal(10,4)','calc_quantity_source'=>'varchar(64)','calc_quantity_source_ref'=>'varchar(128)'] as $key=>$type)if(!isset($actual[$key])||strtolower($actual[$key]['COLUMN_TYPE'])!==$type||$actual[$key]['IS_NULLABLE']!=='YES')throw new RuntimeException('SCHEMA');
 foreach(['public/office-calc-crm-workbench.php','src/Calculation/OfficeCalcCrmDraftLineRepository.php','src/Calculation/OfficeCalcQuantityAdjustments.php'] as $path)if(!is_file($root.'/'.$path)||is_link($root.'/'.$path))throw new RuntimeException('FILES');
 $original=file_get_contents($file);
 if(!is_string($original)||!str_starts_with(ltrim($original),'<?php'))throw new RuntimeException('CONFIG');
 if(($config['OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED']??'')==='enabled'){echo "ACTIVATE=ALREADY_ENABLED\n";exit(0);}
 if(array_key_exists('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED',$config))throw new RuntimeException('EXISTING_FLAG_REQUIRES_MANUAL_REVIEW');
 // Preserve original config including secrets and comments; append a single return-array modifier.
 // Only support a simple terminal return array; refuse other layouts rather than risk overwriting.
 if(!preg_match('/return\\s+(\\[.*\\]);\\s*$/s',$original))throw new RuntimeException('CONFIG_LAYOUT_UNSUPPORTED');
 // Do not reserialize the entire config: this preserves dynamic expressions and comments.
 $modified=preg_replace('/(return\\s+\\[)(.*)(\\];\\s*)$/s',"$1$2\n    'OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED' => 'enabled',\n$3",$original,1,$count);
 if($count!==1||!is_string($modified))throw new RuntimeException('CONFIG_PATCH');
 $backup=$file.'.calc-adjustments-'.gmdate('YmdHis').'.bak';
 if(!copy($file,$backup)||!chmod($backup,0600))throw new RuntimeException('BACKUP');
 $temp=tempnam(dirname($file),'.calc-config-');
 if($temp===false)throw new RuntimeException('TEMP');
 if(file_put_contents($temp,$modified,LOCK_EX)!==strlen($modified)){@unlink($temp);throw new RuntimeException('WRITE');}
 chmod($temp,fileperms($file)&0777);
 $lint=proc_open([PHP_BINARY,'-l',$temp],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 if(!is_resource($lint)){@unlink($temp);throw new RuntimeException('LINT');}
 fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($lint)!==0){@unlink($temp);throw new RuntimeException('LINT');}
 $test=require $temp;
 if(!is_array($test)||($test['OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED']??'')!=='enabled'){@unlink($temp);throw new RuntimeException('VERIFY');}
 if(!rename($temp,$file)){@unlink($temp);throw new RuntimeException('REPLACE');}
 echo "ACTIVATE=PASS\nFLAG_CONFIGURED=YES\nBACKUP=CREATED\n";
}catch(Throwable $e){echo "ACTIVATE=BLOCKED\nREASON=".$e->getMessage()."\n";exit(2);}
