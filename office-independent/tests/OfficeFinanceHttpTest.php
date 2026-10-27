<?php

declare(strict_types=1);

require __DIR__ . '/OfficeFinanceFixture.php';
$directory=sys_get_temp_dir().'/office-finance-'.bin2hex(random_bytes(8));
mkdir($directory,0700);
$file=$directory.'/fixture.sqlite';
$db=financeFixture($file);
$root=dirname(__DIR__);
$runner=$directory.'/request.php';
file_put_contents($runner, <<<'PHP'
<?php
$input=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
foreach ($input['env'] as $key=>$value) putenv($key.'='.$value);
$_SERVER=$input['server']; $_GET=$input['get']; $_COOKIE=$input['cookies'];
register_shutdown_function(static function (): void { echo "\nFINANCE_STATUS=".(http_response_code() ?: 200); });
require $input['endpoint'];
PHP);
function financeRequest(array $changes=[]): array {
  global $runner,$root,$file;
  $request=[
    'env'=>['OFFICE_BASE_PATH'=>'/office-test','OFFICE_CRM_SESSION_MODE'=>'enabled','OFFICE_FINANCE_ENABLED'=>'enabled','OFFICE_FINANCE_SCHEMA_VERIFIED'=>'enabled','OFFICE_PUBLIC_HOST'=>'office.test','OFFICE_INTAKE_PDO_DSN'=>'sqlite:'.$file,'OFFICE_INTAKE_DB_USER'=>'','OFFICE_INTAKE_DB_PASSWORD'=>'','OFFICE_NATIVE_PROJECTS_ENABLED'=>'','OFFICE_RECENT_WORK_ENABLED'=>'','OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED'=>''],
    'server'=>['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'office.test','HTTPS'=>'on','REQUEST_URI'=>'/office-test/finance'],
    'get'=>[], 'cookies'=>['office_test_session'=>str_repeat('s',48)],
    'endpoint'=>$root.'/public/office-finance.php',
  ];
  foreach ($changes as $key=>$value) $request[$key]=in_array($key,['env','server'],true) ? array_replace($request[$key],$value) : $value;
  $process=proc_open([PHP_BINARY,$runner,json_encode($request,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fclose($pipes[0]); $body=stream_get_contents($pipes[1]); fclose($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
  $exit=proc_close($process);
  financeCheck($exit===0 && $error==='' && !str_contains($body,'Warning:') && !str_contains($body,'Fatal error:'),'Request crashed: '.$error.$body);
  financeCheck((bool)preg_match('/FINANCE_STATUS=(\d+)$/',$body,$m),'Response status missing');
  return [(int)$m[1],substr($body,0,strrpos($body,"\nFINANCE_STATUS="))];
}
try {
  [$status,$body]=financeRequest();
  financeCheck($status===200 && str_contains($body,'Werkvoorraad') && !str_contains($body,'Geheime leverancier'),'Dashboard request failed or leaked');
  [$status,$body]=financeRequest(['endpoint'=>$root.'/public/office-finance-payments.php','get'=>['queue'=>'to_approve']]);
  financeCheck($status===200 && str_contains($body,'INV-5') && str_contains($body,'id=5'),'Payment view drill-through failed');
  [$status,$body]=financeRequest(['endpoint'=>$root.'/public/office-finance-invoice.php','get'=>['id'=>'4']]);
  financeCheck($status===200 && str_contains($body,'Factuurcontrole'),'Invoice route failed');
  foreach (['7','8','11','999','-1','1 OR 1=1', ['4']] as $id) {
    [$status,$body]=financeRequest(['endpoint'=>$root.'/public/office-finance-invoice.php','get'=>['id'=>$id]]);
    financeCheck($status===404 && !str_contains($body,'INV-'),'IDOR or invalid ID exposed');
  }
  foreach ([
    [['server'=>['REQUEST_METHOD'=>'POST']],405],
    [['server'=>['HTTPS'=>'off']],403],
    [['server'=>['HTTP_HOST'=>'evil.example']],403],
    [['env'=>['OFFICE_FINANCE_ENABLED'=>'']],503],
    [['env'=>['OFFICE_FINANCE_SCHEMA_VERIFIED'=>'']],503],
    [['env'=>['OFFICE_CRM_SESSION_MODE'=>'']],503],
    [['cookies'=>[]],302],
    [['cookies'=>['office_session'=>str_repeat('s',48)]],302],
    [['cookies'=>['office_test_session'=>str_repeat('x',48)]],302],
  ] as [$input,$expected]) {
    [$status,$body]=financeRequest($input);
    financeCheck($status===$expected && !str_contains($body,'INV-'),'HTTP/auth guard failed');
  }
  [$status,$body]=financeRequest(['endpoint'=>$root.'/public/office-home.php']);
  financeCheck($status===200 && str_contains($body,'Open Finance') && str_contains($body,'/office-test/finance'),'Authorized Finance tile absent');
  $db->exec("DELETE FROM office_module_permissions WHERE operator_id='1'");
  financeCheck(financeRequest()[0]===403,'Revoked module permission accepted by route');
  [$status,$body]=financeRequest(['endpoint'=>$root.'/public/office-home.php']);
  financeCheck($status===200 && !str_contains($body,'Open Finance'),'Unauthorized tile visible');
  $db->exec("INSERT INTO office_module_permissions VALUES ('1','finance','read')");
  $db->exec("UPDATE office_operator_sessions SET expires_at='2000-01-01 00:00:00'");
  financeCheck(financeRequest()[0]===302,'Expired session accepted');
  $db->exec("UPDATE office_operator_sessions SET expires_at='2099-01-01 00:00:00',revoked_at='2026-10-27 00:00:00'");
  financeCheck(financeRequest()[0]===302,'Revoked session accepted');
  $db->exec('UPDATE office_operator_sessions SET revoked_at=NULL');
  $db->exec('UPDATE office_operator_accounts SET active=0');
  financeCheck(financeRequest()[0]===302,'Inactive account accepted');
  $db->exec('UPDATE office_operator_accounts SET active=1');
  $db->exec('DROP TABLE brebo_finance_payment_release');
  [$status,$body]=financeRequest();
  financeCheck($status===503 && !str_contains($body,'Open facturen'),'Missing source presented as zero or partial result');
  echo "OFFICE_FINANCE_HTTP=PASS (routes, tile, IDOR, session expiry/revocation, host, HTTPS, method, unavailable source)\n";
} finally {
  $db=null;
  unlink($runner); unlink($file); rmdir($directory);
}
