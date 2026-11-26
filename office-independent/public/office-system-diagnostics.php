<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$nonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; base-uri 'none'; frame-ancestors 'none'");
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
 'CRM en relaties'=>[['OFFICE_TEST_CRM_ENABLED'],[]],
 'Intake en leads'=>[[],[]],
 'Deals'=>[[],[]],
 'Calculatie'=>[['OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED','OFFICE_CALC_CRM_SCHEMA_VERIFIED'],['office_calc_crm_drafts']],
 'Offertestudio'=>[[],[]],
 'Inkoop'=>[[],[]],
 'FinanceCenter'=>[[],['office_finance_invoice_lines','office_finance_invoice_documents','office_finance_cost_rates']],
 'Documenten'=>[[],[]],
 'E-mail'=>[[],[]],
 'Kennis en kwaliteit'=>[[],[]],
 'Oplevering en snagging'=>[[],[]]];
 $routes=[
 'Projecten'=>['/office-test/projecten','office-projects.php'],
 'Gebouwen en woningtypen'=>['/office-test/gebouwen','office-buildings.php'],
 'Planning en inzet'=>['/office-test/inzet/dienst/nieuw','office-inzet-shift-create.php'],
 'OnSite'=>['/office-test/inzet/onsite/aanwezigheid','office-onsite-presence-review.php'],
 'Urencontrole'=>['/office-test/inzet/onsite/uren-beoordelen','office-onsite-presence-review.php'],
 'CRM en relaties'=>['/office-test/crm/relaties','office-crm-relations.php'],
 'Intake en leads'=>['/office-test/crm/aanvragen','crm-new-requests.php'],
 'Deals'=>['/office-test/crm/verkoopkansen','office-crm-opportunities.php'],
 'Calculatie'=>['/office-test/calculatie','office-calc-crm-workbench.php'],
 'Inkoop'=>['/office-test/inkoop','office-governance.php'],
 'FinanceCenter'=>['/office-test/finance/administratie','office-finance-center.php'],
 'E-mail'=>['/office-test/email','office-email.php'],
 'Kennis en kwaliteit'=>['/office-test/kwaliteit','office-governance.php']];
 $selected=$_GET['module']??null;
 if ($selected!==null && (!is_string($selected)||!array_key_exists($selected,$groups))) {http_response_code(400);exit('Onbekende module.');}
 $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $html='<nav>';
 foreach ($groups as $name=>$profile) {
 $html.='<a class="module" href="?module='.rawurlencode($name).'">'.$e($name).' — Diagnose &amp; prompt</a>';
 }
 $html.='</nav>';
 if ($selected!==null) {
 [$flags,$tables]=$groups[$selected];
 $lines=['Repository: JohnBN1970/brebo-office','Doelbranch: develop','Omgeving: Office-test','Module: '.$selected,'Controle UTC: '.gmdate('c'),'PHP: '.PHP_VERSION,'Database: brebo_office_test (bevestigd)'];
 $html.='<section><h2>'.$e($selected).'</h2><table>';
 foreach ($flags as $flag) {$v=getenv($flag);$status=$v===false?'ONTBREEKT':($v==='enabled'?'ACTIEF':'NIET ACTIEF');$lines[]=$flag.': '.$status;$html.='<tr><td>'.$e($flag).'</td><td>'.$status.'</td></tr>';}
 foreach ($tables as $table) {$q->execute([$table]);$status=(int)$q->fetchColumn()===1?'AANWEZIG':'ONTBREEKT';$lines[]=$table.': '.$status;$html.='<tr><td>'.$e($table).'</td><td>'.$status.'</td></tr>';}
 if (!$flags && !$tables) {$lines[]='Geen specifieke technische controles geregistreerd.';}
 $lines[]='Functionele ketentest: niet uitgevoerd. Schema-aanwezigheid is geen volledige schemavalidatie.';
 $release='Niet vastgesteld';
 foreach (explode(DIRECTORY_SEPARATOR,__DIR__) as $part) {
 if (preg_match('/\A[0-9a-f]{40}\z/D',$part)) {$release=$part.' (releasepad, niet onafhankelijk geverifieerd)';}
 }
 $lines[]='Release: '.$release;
 if (isset($routes[$selected])) {
 [$route,$handler]=$routes[$selected];
 $router=file_get_contents(__DIR__.'/index.php');
 $registered=is_string($router)&&str_contains($router,"'".$route."' => '".$handler."'");
 $status=$registered&&is_file(__DIR__.'/'.$handler)?'STATISCH AANWEZIG':'NIET BEVESTIGD';
 $lines[]='Route '.$route.': '.$status.' (geen HTTP-test)';
 $html.='<tr><td>'.$e($route).'</td><td>'.$status.'</td></tr>';
 } else {$lines[]='Route: geen specifieke controle geregistreerd.';}
 $lines[]='Modulerechten, koppelingen en achtergrondprocessen: niet gecontroleerd.';
 $prompt="Analyseer uitsluitend deze module. Controleer eerst de actuele repository en actieve testrelease. Verifieer afwijkingen en ontbrekende controles; een uitgeschakelde optionele flag is niet automatisch een fout. Hergebruik bestaande functionaliteit. Herstel uitsluitend bewezen problemen via een afzonderlijke Git-branch, voeg regressietesten toe en rapporteer blokkades. Wijzig productie niet. Vraag geen geheimen op.\n\n".implode("\n",$lines);
 $html.='</table><p>'.$e(implode(' ',array_slice($lines,-2))).'</p><h3>AI-herstelprompt</h3><p>Selecteer de volledige tekst en kopieer deze naar ChatGPT of Codex.</p><button type="button" id="copy-prompt">Kopieer prompt</button><span id="copy-status" role="status"></span><textarea id="repair-prompt" readonly rows="18" aria-label="AI-herstelprompt">'.$e($prompt).'</textarea><p><a href="?module='.rawurlencode($selected).'">Diagnose opnieuw uitvoeren</a></p></section>';
 }
 echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Systeemdiagnostiek</title><style>body{font:15px system-ui;margin:24px;color:#24364a}section{border:1px solid #ccd5df;padding:16px;margin:12px 0}td{padding:6px;overflow-wrap:anywhere}table{width:100%;table-layout:fixed}nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px}.module{padding:12px;border:1px solid #ccd5df;border-radius:6px}textarea{box-sizing:border-box;width:100%;font:14px monospace;padding:12px}</style><a href="/office-test/instellingen">Instellingen</a><h1>Systeemdiagnostiek — Office</h1><p>Alleen lezen. Selecteer een module voor actuele technische controles en een AI-herstelprompt.</p>'.$html.'<script nonce="'.$e($nonce).'">const b=document.getElementById("copy-prompt");if(b)b.addEventListener("click",async()=>{const t=document.getElementById("repair-prompt"),s=document.getElementById("copy-status");try{await navigator.clipboard.writeText(t.value);s.textContent=" Gekopieerd";}catch(e){t.focus();t.select();s.textContent=" Tekst geselecteerd; kopieer met Ctrl+C.";}});</script></html>';
} catch (Throwable $e) {http_response_code(503);exit('Diagnostiek tijdelijk niet beschikbaar.');}
