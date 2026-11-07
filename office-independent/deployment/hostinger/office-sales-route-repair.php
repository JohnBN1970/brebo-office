<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); umask(0077);
$changed=false; $before=''; $after=''; $file=''; $mode=0;
function salesRouteStatus(string $path): int {
  $curl=curl_init('https://brebobv.nl'.$path);
  curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);
  try { if(curl_exec($curl)===false)throw new RuntimeException('HTTP verification unavailable');return (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); } finally { curl_close($curl); }
}
function salesRouteReplace(string $file,string $expected,string $bytes,int $mode): void {
  if(is_link($file)||realpath($file)!==$file||hash_file('sha256',$file)!==$expected)throw new RuntimeException('Concurrent rewrite change');
  $tmp=tempnam(dirname($file),'.sales-route-');
  try {chmod($tmp,$mode);if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||hash_file('sha256',$file)!==$expected||!rename($tmp,$file))throw new RuntimeException('Rewrite replacement failed');}finally{if(is_file($tmp))unlink($tmp);}
}
try {
  $stage=dirname(__DIR__,2);
  if(($argv[1]??'')!=='--repair-route-only'||!preg_match('/^office-sales-stage-\d+-\d+$/D',basename($stage)))throw new RuntimeException('Explicit test route repair required');
  $base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';
  $configFile=$base.'/config/config.php';
  if(is_link($configFile)||realpath($configFile)!==$configFile)throw new RuntimeException('Unsafe test config');
  $config=require $configFile; $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($root)||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')||realpath($root)!==$root
    ||($config['OFFICE_SALES_ENABLED']??'')!=='enabled'||($config['OFFICE_SALES_SCHEMA_VERIFIED']??'')!=='enabled'||($config['OFFICE_SALES_SEND_ENABLED']??'disabled')!=='disabled'||($config['OFFICE_SALES_REMINDERS_ENABLED']??'disabled')!=='disabled')throw new RuntimeException('Wrong test scope');
  if(!is_file($root.'/modules/sales/routes.php')||!is_file($root.'/public/office-sales.php'))throw new RuntimeException('Installed module required');
  $file=dirname($base).'/public_html/office-test/.htaccess';
  if(is_link($file)||realpath($file)!==$file)throw new RuntimeException('Unsafe rewrite path');
  $before=file_get_contents($file);$mode=fileperms($file)&0777;
  $anchor='RewriteEngine On';$rule='RewriteRule ^finance/verkoop$ index.php [END,QSA]';
  if(substr_count($before,$anchor)!==1||str_contains($before,'finance/verkoop'))throw new RuntimeException('Existing rewrite requires review');
  $after=str_replace($anchor,$anchor."\n# Office-native sales; exact isolated route only.\n".$rule,$before);
  $financeBefore=salesRouteStatus('/office-test/finance');
  if(!in_array($financeBefore,[200,302,403],true))throw new RuntimeException('Existing Finance unavailable');
  if(file_put_contents($stage.'/sales-rewrite-before.htaccess',$before,LOCK_EX)!==strlen($before)||hash_file('sha256',$stage.'/sales-rewrite-before.htaccess')!==hash('sha256',$before))throw new RuntimeException('Backup failed');
  salesRouteReplace($file,hash('sha256',$before),$after,$mode);$changed=true;
  $sales=salesRouteStatus('/office-test/finance/verkoop');$financeAfter=salesRouteStatus('/office-test/finance');
  echo 'OFFICE_SALES_HTTP_STATUS='.$sales."\nOFFICE_FINANCE_HTTP_STATUS=".$financeAfter."\n";
  if(!in_array($sales,[200,302,403],true)||$financeAfter!==$financeBefore)throw new RuntimeException('Route verification failed');
  echo "OFFICE_SALES_REWRITE_ACTIVE=1\nOFFICE_SALES_CONFIG_UNCHANGED=1\nOFFICE_SALES_NO_DATABASE_WRITES=1\n";
} catch(Throwable $e) {
  if($changed) {try{salesRouteReplace($file,hash('sha256',$after),$before,$mode);echo "OFFICE_SALES_REWRITE_RESTORED=1\n";}catch(Throwable $restore){echo "OFFICE_SALES_REWRITE_RESTORE_REQUIRES_REVIEW=1\n";}}
  echo "OFFICE_SALES_ROUTE_REPAIR_FAILED=1\n";exit(1);
}
