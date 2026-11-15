<?php
declare(strict_types=1);
// File-only Office-test UI deployment. No database, flags, or router changes.
// Usage: php install-calc-grid-only.php /absolute/path/to/staged-workbench.php <expected-current-sha256>
$home=(string)getenv('HOME');
$base=realpath($home.'/domains/brebobv.nl/office_calc_test');
$stage=realpath($argv[1]??'');
$expected=$argv[2]??'';
if($base===false||$stage===false||!str_starts_with($stage,$base.'/.calc-grid-stage-')
 || !preg_match('/^[a-f0-9]{64}$/D',$expected))exit(2);
$configPath=$base.'/config/config.php';
$config=is_file($configPath)?require $configPath:null;
$root=is_array($config)?realpath((string)($config['OFFICE_PRIVATE_ROOT']??'')):false;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'
 ||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$target=$root.'/public/office-calc-crm-workbench.php';
if(!is_file($target)||is_link($target)||!is_file($stage)||is_link($stage))exit(4);
$lock=fopen($base.'/.calc-grid-install.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(5);
try{
 $actual=hash_file('sha256',$target);
 if(!hash_equals($expected,$actual))throw new RuntimeException('target_changed');
 $current=(string)file_get_contents($target);
 $incoming=(string)file_get_contents($stage);
 foreach(['Gezamenlijke calculaties','OfficeCalcCrmDraftLineRepository','office_project_csrf',
          'office-calc-crm-collaboration-panels.php','office-calc-crm-material-panel.php']as$guard)
  if(!str_contains($incoming,$guard))throw new RuntimeException('missing_contract');
 if(!str_contains($incoming,'element-tree'))throw new RuntimeException('grid_missing');
 $lint=static function(string $file):void{
  $proc=proc_open([PHP_BINARY,'-l',$file],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
  if(!is_resource($proc))throw new RuntimeException('lint_start');
  fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
  fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($proc)!==0)throw new RuntimeException('lint_failed: '.$out.$err);
 };
 $lint($stage);
 $stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
 $backupDir=$base.'/.calc-grid-backups';
 if(!is_dir($backupDir)&&!mkdir($backupDir,0700,true))throw new RuntimeException('backup_dir');
 $backup=$backupDir.'/'.$stamp.'.php';
 if(file_put_contents($backup,$current,LOCK_EX)!==strlen($current))throw new RuntimeException('backup_failed');
 chmod($backup,0600);
 $tmp=tempnam(dirname($target),'.calc-grid-');
 if($tmp===false)throw new RuntimeException('temp_failed');
 try{
  if(file_put_contents($tmp,$incoming)!==strlen($incoming))throw new RuntimeException('write_failed');
  chmod($tmp,fileperms($target)&0777);
  if(!rename($tmp,$target))throw new RuntimeException('swap_failed');
 }finally{if(is_file($tmp))unlink($tmp);}
 try{$lint($target);}
 catch(Throwable $error){
  if(hash_equals(hash('sha256',$incoming),(string)hash_file('sha256',$target)))
   copy($backup,$target);
  throw $error;
 }
 echo 'CALC_GRID_INSTALL=SUCCESS'."\n";
 echo 'CALC_GRID_BACKUP='.$backup."\n";
 echo 'CALC_GRID_NEW_SHA256='.hash_file('sha256',$target)."\n";
 echo 'CALC_GRID_DATABASE=UNCHANGED'."\n";
 echo 'CALC_GRID_ROUTER=UNCHANGED'."\n";
}catch(Throwable $error){
 echo 'CALC_GRID_INSTALL=BLOCKED_'.$error->getMessage()."\n";exit(10);
}finally{flock($lock,LOCK_UN);fclose($lock);}
