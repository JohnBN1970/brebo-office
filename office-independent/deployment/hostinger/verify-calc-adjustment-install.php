<?php
declare(strict_types=1);
// Read-only post-deployment verification of all three installed PHP files.
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$stage=realpath($argv[1]??'');
if(!$base||!$stage||!str_starts_with($stage,$base.'/.calc-grid-stage-'))exit(2);
$configPath=$base.'/config/config.php';
$config=is_file($configPath)?require $configPath:null;
$root=is_array($config)?realpath((string)($config['OFFICE_PRIVATE_ROOT']??'')):false;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!$root||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$paths=[
 'workbench.php'=>'public/office-calc-crm-workbench.php',
 'OfficeCalcCrmDraftLineRepository.php'=>'src/Calculation/OfficeCalcCrmDraftLineRepository.php',
 'OfficeCalcQuantityAdjustments.php'=>'src/Calculation/OfficeCalcQuantityAdjustments.php',
];
foreach($paths as $name=>$path){
 $src=$stage.'/'.$name;
 $dest=$root.'/'.$path;
 if(!is_file($src)||is_link($src)||!is_file($dest)||is_link($dest))exit(4);
 if(!hash_equals((string)hash_file('sha256',$src),(string)hash_file('sha256',$dest))) {
  echo "CALC_VERIFY=HASH_MISMATCH\n";
  exit(5);
 }
 $process=proc_open([PHP_BINARY,'-l',$dest],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 if(!is_resource($process))exit(6);
 fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($process)!==0)exit(7);
}
echo "CALC_VERIFY=PASS\nCALC_VERIFIED_FILES=3\n";
