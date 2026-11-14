<?php
declare(strict_types=1);
// Guarded multi-file installation. Does NOT mutate the database or feature flags.
// php install-calc-adjustment-files.php <staging-dir> <expected-manifest.json>
// Manifest: {"workbench.php":"sha256","OfficeCalcCrmDraftLineRepository.php":"sha256","OfficeCalcQuantityAdjustments.php":"sha256"}
// Manifest hashes are of currently installed files; new files use null.
$home=(string)getenv('HOME');
$base=realpath($home.'/domains/brebobv.nl/office_calc_test');
$stage=realpath($argv[1]??'');
$manifestPath=realpath($argv[2]??'');
if($base===false||$stage===false||$manifestPath===false||!str_starts_with($stage,$base.'/.calc-grid-stage-')||!str_starts_with($manifestPath,$stage.'/'))exit(2);
$configPath=$base.'/config/config.php';
$config=is_file($configPath)?require $configPath:null;
$root=is_array($config)?realpath((string)($config['OFFICE_PRIVATE_ROOT']??'')):false;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$files=[
 'workbench.php'=>'public/office-calc-crm-workbench.php',
 'OfficeCalcCrmDraftLineRepository.php'=>'src/Calculation/OfficeCalcCrmDraftLineRepository.php',
 'OfficeCalcQuantityAdjustments.php'=>'src/Calculation/OfficeCalcQuantityAdjustments.php',
 'OfficeCalcLaborNorm.php'=>'src/Calculation/OfficeCalcLaborNorm.php',
];
$expected=json_decode((string)file_get_contents($manifestPath),true);
if(!is_array($expected)||array_keys($expected)!==array_keys($files))exit(4);
$lock=fopen($base.'/.calc-grid-install.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(5);
$backups=[];$installed=[];
try{
 foreach($files as $name=>$relative){
  $src=$stage.'/'.$name;$dest=$root.'/'.$relative;
  if(!is_file($src)||is_link($src)||is_link($dest)||!is_dir(dirname($dest)))throw new RuntimeException('invalid_file_'.$name);
  $sha=$expected[$name];
  if($sha!==null&&(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)))throw new RuntimeException('invalid_hash_'.$name);
  if($sha===null&&is_file($dest))throw new RuntimeException('unexpected_existing_'.$name);
  if($sha!==null&&(!is_file($dest)||!hash_equals($sha,hash_file('sha256',$dest))))throw new RuntimeException('changed_'.$name);
  $process=proc_open([PHP_BINARY,'-l',$src],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('lint_start_'.$name);
  fclose($pipes[0]);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($process)!==0)throw new RuntimeException('lint_'.$name.':'.$output);
 }
 $backupDir=$base.'/.calc-grid-backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));
 if(!mkdir($backupDir,0700,true))throw new RuntimeException('backup_directory');
 foreach($files as $name=>$relative){
  $dest=$root.'/'.$relative;
  if(is_file($dest)){
   $backup=$backupDir.'/'.$name;
   if(!copy($dest,$backup))throw new RuntimeException('backup_'.$name);
   chmod($backup,0600);$backups[$dest]=$backup;
  }else $backups[$dest]=null;
 }
 foreach($files as $name=>$relative){
  $dest=$root.'/'.$relative;$src=$stage.'/'.$name;
  $tmp=tempnam(dirname($dest),'.calc-release-');
  if($tmp===false)throw new RuntimeException('temp_'.$name);
  try{
   if(!copy($src,$tmp))throw new RuntimeException('copy_'.$name);
   chmod($tmp,is_file($dest)?(fileperms($dest)&0777):0640);
   if(!rename($tmp,$dest))throw new RuntimeException('rename_'.$name);
  }finally{if(is_file($tmp))unlink($tmp);}
  $installed[]=$dest;
 }
 foreach($files as $name=>$relative){
  $dest=$root.'/'.$relative;
  $process=proc_open([PHP_BINARY,'-l',$dest],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('postlint_start_'.$name);
  fclose($pipes[0]);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($process)!==0)throw new RuntimeException('postlint_'.$name.':'.$output);
 }
 echo "CALC_FILES_INSTALL=SUCCESS\nCALC_FILES_COUNT=4\nCALC_DATABASE=UNCHANGED\nCALC_FLAGS=UNCHANGED\n";
}catch(Throwable $error){
 $rollbackOk=true;
 foreach(array_reverse($installed) as $dest){
  $backup=$backups[$dest]??null;
  if($backup!==null){if(!copy($backup,$dest))$rollbackOk=false;}
  elseif(is_file($dest)&&!unlink($dest))$rollbackOk=false;
 }
 echo 'CALC_FILES_INSTALL=BLOCKED_'.preg_replace('/[^A-Za-z0-9_]/','_',substr($error->getMessage(),0,100))."\n";
 echo 'ROLLBACK='.($rollbackOk?'DONE':'FAILED')."\n";
 exit(10);
}finally{flock($lock,LOCK_UN);fclose($lock);}
