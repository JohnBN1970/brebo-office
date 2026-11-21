<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) {http_response_code(405);exit;}
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
use Brebo\Office\Intake\OfficeMount;
if (!OfficeMount::isTest() || getenv('OFFICE_TEST_BLUEPRINT_APPROVAL_MODES_ENABLED') !== 'enabled'
    || getenv('OFFICE_TEST_BLUEPRINT_ADMIN_ENABLED') !== 'enabled') {http_response_code(404);exit;}
$secret=getenv('OFFICE_TEST_BLUEPRINT_ADMIN_CSRF_KEY');
if (!is_string($secret) || strlen($secret)<32) {http_response_code(503);exit('Beheerconfiguratie ontbreekt.');}
foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
 'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
 require_once dirname(__DIR__).'/src/Intake/'.$file;
}
require_once dirname(__DIR__).'/src/Approval/OfficeApprovalPolicy.php';
require_once dirname(__DIR__).'/src/Approval/OfficeBlueprintApprovalModeRepository.php';
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
try {
 $dsn=getenv('OFFICE_INTAKE_PDO_DSN');
 if (!is_string($dsn)||$dsn==='') throw new RuntimeException('DB unavailable');
 $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
   [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $sessions=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
   new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$sessions->currentOperator();
 if ($operator===null) {http_response_code(401);exit('Aanmelden vereist.');}
 if (!(new \Brebo\Office\Approval\OfficeTestSiteAdministratorAccess($db))
   ->isAuthorized($operator->operatorId,true)) {http_response_code(403);exit('Alleen sitebeheerder.');}
 $csrf=hash_hmac('sha256','blueprint-approval:'.$operator->sessionId,$secret);
 $repo=new \Brebo\Office\Approval\OfficeBlueprintApprovalModeRepository($db);
 if ($_SERVER['REQUEST_METHOD']==='POST') {
   $submitted=$_POST['csrf']??null;
   if (!is_string($submitted)||!hash_equals($csrf,$submitted)) {http_response_code(403);exit('Formulierbeveiliging ongeldig.');}
   $mode=($_POST['four_eyes']??null)==='on'?'four_eyes':'single';
   $repo->set('project_start','startdossier_release',$mode,(int)$operator->operatorId,true,true);
   header('Location: /office-test/instellingen/blauwdrukken?opgeslagen=1',true,303);exit;
 }
 $mode=$repo->get('project_start','startdossier_release');
} catch (Throwable $e) {
 error_log('Office-test blueprint approval settings unavailable: '.$e->getMessage());
 http_response_code(503);exit('Blauwdrukinstellingen zijn tijdelijk niet beschikbaar.');
}
$checked=$mode==='four_eyes'?' checked':'';
$saved=($_GET['opgeslagen']??'')==='1'?'<p role="status">Instelling opgeslagen.</p>':'';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Office-test | Blauwdrukken</title><style>body{font:16px system-ui;margin:3rem auto;max-width:760px;padding:0 1rem;background:#f5f7fb;color:#182d42}main{background:white;padding:2rem;border:1px solid #dae2ec;border-radius:12px}label{display:block;margin:1.5rem 0}button{background:#245b90;color:white;border:0;border-radius:6px;padding:.8rem 1.4rem;cursor:pointer}small{color:#50647a}</style></head><body><main><a href="/office-test/">← Office</a><h1>Flows en blauwdrukken</h1><h2>Projectstart · Startdossier vrijgeven</h2>'.$saved.'<form method="post" action="/office-test/instellingen/blauwdrukken"><input type="hidden" name="csrf" value="'.$csrf.'"><label><input type="checkbox" name="four_eyes" value="on"'.$checked.'> Vierogenprincipe verplicht</label><p><small>Uitgevinkt: één bevoegde goedkeurder. Alleen Office-test. Wijzigingen worden vastgelegd in de auditregistratie.</small></p><button type="submit">Instelling opslaan</button></form></main></body></html>';
