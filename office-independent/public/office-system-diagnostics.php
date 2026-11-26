<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {header('Allow: GET');http_response_code(405);exit;}
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest() || ($_SERVER['HTTP_HOST']??'')!=='office-test.brebobv.nl' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(404);exit;}
foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
try {
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') {http_response_code(503);exit('Testdatabase niet bevestigd.');}
 $sessions=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$sessions->currentOperator();
 if ($operator===null) {http_response_code(401);exit('Aanmelden vereist.');}
 if (!(new \Brebo\Office\Approval\OfficeTestSiteAdministratorAccess($db))->isAuthorized((string)$operator->operatorId,true)) {http_response_code(403);exit('Alleen sitebeheerder.');}
 $groups=[
 'Projecten'=>[['OFFICE_NATIVE_PROJECTS_ENABLED','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'],['office_projects','office_project_buildings']],
 'Gebouwen en woningtypen'=>[[],['office_buildings','office_housing_types','office_project_housing_types','office_project_calculation_scopes','office_scope_quantity_lines']],
 'Startdossier'=>[['OFFICE_TEST_PROJECT_KICKSTART_ENABLED'],[]],
 'Planning en inzet'=>[['OFFICE_TEST_INZET_SHIFTS_ENABLED','OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED','OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED'],[]],
 'OnSite'=>[['OFFICE_ONSITE_SCHEMA_VERIFIED','OFFICE_TEST_PRESENCE_REVIEW_ENABLED','OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED','OFFICE_TEST_ZONE_WRITE_ENABLED','OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED'],[]],
 'Urencontrole'=>[['OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED','OFFICE_TEST_ONSITE_FOUR_EYES_REQUIRED','OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED'],['office_onsite_hours_decisions']],
 'Werkbegroting en overdracht'=>[[],['office_calc_crm_acceptance_transfers']],
 'Inkoop en finance'=>[[],[]],
 'Documenten en e-mail'=>[[],[]]];
 $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $html='';
 foreach ($groups as $name=>[$flags,$tables]) {
 $html.='<section><h2>'.$e($name).'</h2><table>';
 foreach ($flags as $flag) {$v=getenv($flag);$status=$v===false?'ONTBREEKT':($v==='enabled'?'ACTIEF':'NIET ACTIEF');$html.='<tr><td>'.$e($flag).'</td><td>'.$status.'</td></tr>';}
 foreach ($tables as $table) {$q->execute([$table]);$html.='<tr><td>'.$e($table).'</td><td>'.((int)$q->fetchColumn()===1?'AANWEZIG':'ONTBREEKT').'</td></tr>';}
 $html.='</table><p>Functionele ketentest: niet uitgevoerd. Schema-aanwezigheid is geen volledige schemavalidatie.</p></section>';
 }
 echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Systeemdiagnostiek</title><style>body{font:15px system-ui;margin:24px;color:#24364a}section{border:1px solid #ccd5df;padding:16px;margin:12px 0}td{padding:6px;overflow-wrap:anywhere}table{width:100%;table-layout:fixed}</style><a href="/office-test/instellingen">Instellingen</a><h1>Systeemdiagnostiek — Projecten</h1><p>Alleen lezen. Actieve PHP-FPM-instellingen en tabelaanwezigheid.</p>'.$html.'</html>';
} catch (Throwable $e) {http_response_code(503);exit('Diagnostiek tijdelijk niet beschikbaar.');}
