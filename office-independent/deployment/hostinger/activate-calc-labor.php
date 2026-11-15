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
 foreach(['calc_labor_norm'=>'decimal(12,4)','calc_labor_hours'=>'decimal(14,4)','calc_labor_hourly_rate'=>'decimal(12,4)','calc_labor_driver'=>'varchar(8)'] as $key=>$type)if(!isset($actual[$key])||strtolower($actual[$key]['COLUMN_TYPE'])!==$type||$actual[$key]['IS_NULLABLE']!=='YES')throw new RuntimeException('SCHEMA');
 foreach(['public/office-calc-crm-workbench.php','src/Calculation/OfficeCalcCrmDraftLineRepository.php','src/Calculation/OfficeCalcLaborNorm.php'] as $path)if(!is_file($root.'/'.$path)||is_link($root.'/'.$path))throw new RuntimeException('FILES');
 $original=file_get_contents($file);
 if(!is_string($original)||!str_starts_with(ltrim($original),'<?php'))throw new RuntimeException('CONFIG');
 if(($config['OFFICE_CALC_LABOR_SCHEMA_VERIFIED']??'')==='enabled'){echo "ACTIVATE=ALREADY_ENABLED\n";exit(0);}
 if(array_key_exists('OFFICE_CALC_LABOR_SCHEMA_VERIFIED',$config)&&$config['OFFICE_CALC_LABOR_SCHEMA_VERIFIED']!=='disabled')throw new RuntimeException('EXISTING_FLAG_REQUIRES_MANUAL_REVIEW');
 // Safely wrap the terminal top-level PHP return expression, regardless of
 // whether config.php uses return $config, return [...], or another expression.
 // Preserve every original byte outside that return statement.
 $tokens=token_get_all($original);
 $offset=0;$depth=0;$returnStart=null;$returnEnd=null;$returnCount=0;
 foreach($tokens as $token){
  $piece=is_array($token)?$token[1]:$token;
  if(is_array($token)&&$token[0]===T_RETURN&&$depth===0){
   $returnCount++;
   if($returnCount>1)throw new RuntimeException('MULTIPLE_TOP_LEVEL_RETURNS');
   $returnStart=$offset;
  }elseif($piece==='{'){$depth++;}
  elseif($piece==='}'){$depth--;}
  if($returnStart!==null&&$returnEnd===null&&$piece===';'&&$depth===0)$returnEnd=$offset+1;
  $offset+=strlen($piece);
 }
 if($returnCount!==1||$returnStart===null||$returnEnd===null||$depth!==0)throw new RuntimeException('CONFIG_RETURN_UNSUPPORTED');
 $tail=substr($original,$returnEnd);
 if(!preg_match('/^\\s*(?:\\?>\\s*)?$/D',$tail))throw new RuntimeException('CONFIG_HAS_TRAILING_CODE');
 $expr=trim(substr($original,$returnStart+6,$returnEnd-$returnStart-7));
 if($expr==='')throw new RuntimeException('CONFIG_EMPTY_RETURN');
 $modified=substr($original,0,$returnStart)
  ."return array_replace((". $expr ."), ['OFFICE_CALC_LABOR_SCHEMA_VERIFIED'=>'enabled']);"
  .substr($original,$returnEnd);
 $backup=$file.'.calc-labor-'.gmdate('YmdHis').'.bak';
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
 if(!is_array($test)||($test['OFFICE_CALC_LABOR_SCHEMA_VERIFIED']??'')!=='enabled'){@unlink($temp);throw new RuntimeException('VERIFY');}
 foreach($config as $key=>$value)if(($test[$key]??null)!==$value&&$key!=='OFFICE_CALC_LABOR_SCHEMA_VERIFIED'){@unlink($temp);throw new RuntimeException('CONFIG_VALUE_CHANGED');}
 if(!rename($temp,$file)){@unlink($temp);throw new RuntimeException('REPLACE');}
 echo "ACTIVATE=PASS\nFLAG_CONFIGURED=YES\nBACKUP=CREATED\n";
}catch(Throwable $e){echo "ACTIVATE=BLOCKED\nREASON=".$e->getMessage()."\n";exit(2);}
