<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
$phase='scope';
try {
  $domain=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl';
  $file=$domain.'/office_calc_test/config/config.php';
  ob_start(); try { $config=require $file; } finally { ob_end_clean(); }
  $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if (($config['OFFICE_BASE_PATH']??'')!=='/office-test' || !str_starts_with($root,$domain.'/office_calc_test/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root) throw new RuntimeException('Wrong test scope');
  $phase='web_scope';
  $web=realpath($domain.'/public_html/office-test');
  $domainReal=realpath($domain);
  if ($web===false || $domainReal===false || !str_starts_with($web,$domainReal.'/') || !is_file($web.'/index.php')) throw new RuntimeException('Wrong web scope');
  $phase='install';
  $source=__DIR__.'/office-moneybird-bank-account-check.php';
  if (!preg_match('/^office-bank-staging-(\d+)-(\d+)$/D',basename(dirname(__DIR__,2)),$run)) throw new RuntimeException('Invalid run');
  $name='bank-account-check-'.$run[1].'-'.$run[2].'.php';
  $target=$web.'/'.$name;
  if (file_exists($target) || is_link($target)) {
    if (!is_file($target) || is_link($target) || hash_file('sha256',$target)!==hash_file('sha256',$source)) throw new RuntimeException('Unknown target file');
  } else {
    $phase='create';
    $body=file_get_contents($source);
    $out=fopen($target,'x');
    if ($out===false) throw new RuntimeException('Cannot create check');
    try {
      if (fwrite($out,$body)!==strlen($body)) throw new RuntimeException('Incomplete check');
    } finally { fclose($out); }
    chmod($target,0644);
  }
  echo "OFFICE_BANK_SELF_ACCOUNT_CHECK_READY=1\nOFFICE_BANK_SELF_ACCOUNT_CHECK_PATH=/office-test/".$name."\n";
} catch (Throwable $ignored) { echo "OFFICE_BANK_SELF_ACCOUNT_CHECK_READY=0\nOFFICE_BANK_SELF_ACCOUNT_CHECK_FAILED_PHASE=".$phase."\n"; exit(1); }
