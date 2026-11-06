<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
try {
  $domain=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl';
  $file=$domain.'/office_calc_test/config/config.php';
  ob_start(); try { $config=require $file; } finally { ob_end_clean(); }
  $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if (($config['OFFICE_BASE_PATH']??'')!=='/office-test' || !str_starts_with($root,$domain.'/office_calc_test/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root) throw new RuntimeException('Wrong test scope');
  $web=$domain.'/public_html/office-test';
  if (realpath($web)!==$web || !is_file($web.'/index.php')) throw new RuntimeException('Wrong web scope');
  $source=__DIR__.'/office-moneybird-bank-account-check.php';
  $target=$web.'/bank-account-check.php';
  if (file_exists($target) || is_link($target)) {
    if (!is_file($target) || is_link($target) || hash_file('sha256',$target)!==hash_file('sha256',$source)) throw new RuntimeException('Unknown target file');
  } else {
    $temp=tempnam($web,'.bank-account-');
    try {
      $body=file_get_contents($source);
      if (file_put_contents($temp,$body,LOCK_EX)!==strlen($body)) throw new RuntimeException('Incomplete check');
      chmod($temp,0644);
      if (!link($temp,$target)) throw new RuntimeException('Target changed');
    } finally { if (is_file($temp)) unlink($temp); }
  }
  echo "OFFICE_BANK_SELF_ACCOUNT_CHECK_READY=1\n";
} catch (Throwable $ignored) { echo "OFFICE_BANK_SELF_ACCOUNT_CHECK_READY=0\n"; exit(1); }
