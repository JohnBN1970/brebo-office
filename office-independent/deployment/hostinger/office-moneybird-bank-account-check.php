<?php
declare(strict_types=1);
ini_set('display_errors','0');
// Temporary self-account diagnostic. No financial data, secrets or permission writes.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
try {
  if (($_SERVER['REQUEST_METHOD']??'')!=='GET') { http_response_code(405); exit; }
  $domain=dirname(__DIR__,2);
  $base=$domain.'/office_calc_test';
  $file=$base.'/config/config.php';
  $level=ob_get_level(); ob_start();
  try { $config=require $file; } finally { while(ob_get_level()>$level) ob_end_clean(); }
  $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if (!is_array($config) || ($config['OFFICE_CRM_SESSION_MODE']??'')!=='enabled' || ($config['OFFICE_BASE_PATH']??'')!=='/office-test'
      || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root
      || ($config['OFFICE_PUBLIC_HOST']??'')!==($_SERVER['HTTP_HOST']??'')
      || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(404); exit; }
  putenv('OFFICE_BASE_PATH=/office-test');
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $class) require_once $root.'/src/Intake/'.$class;
  require_once $root.'/src/Finance/OfficeFinanceAccess.php';
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if ($operator===null) { http_response_code(401); echo '{"signed_in":false}'; exit; }
  echo json_encode(['signed_in'=>true,'account_id'=>$operator->operatorId,'finance_read'=>(new \Brebo\Office\Finance\OfficeFinanceAccess($db,$operator->operatorId))->allows('read')],JSON_THROW_ON_ERROR);
} catch (Throwable $ignored) { http_response_code(503); echo '{"account_check_available":false}'; }
