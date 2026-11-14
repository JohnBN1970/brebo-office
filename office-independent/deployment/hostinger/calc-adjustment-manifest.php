<?php
declare(strict_types=1);
// Run on Hostinger. Read-only target inspection; writes expected.json only in staging.
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$stage=realpath($argv[1]??'');
if(!$base||!$stage||!str_starts_with($stage,$base.'/.calc-grid-stage-'))exit(2);
$config=require $base.'/config/config.php';
$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!$root||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$paths=[
 'workbench.php'=>'public/office-calc-crm-workbench.php',
 'OfficeCalcCrmDraftLineRepository.php'=>'src/Calculation/OfficeCalcCrmDraftLineRepository.php',
 'OfficeCalcQuantityAdjustments.php'=>'src/Calculation/OfficeCalcQuantityAdjustments.php',
];
$manifest=[];
foreach($paths as $name=>$path){
 $file=$root.'/'.$path;
 if(is_link($file)||(!is_file($file)&&file_exists($file)))exit(4);
 $manifest[$name]=is_file($file)?hash_file('sha256',$file):null;
}
if(file_put_contents($stage.'/expected.json',json_encode($manifest,JSON_THROW_ON_ERROR),LOCK_EX)===false)exit(5);
chmod($stage.'/expected.json',0600);
echo "CALC_MANIFEST=READY\n";
