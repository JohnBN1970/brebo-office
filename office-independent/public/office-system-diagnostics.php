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
 $schemas=[
 'Projecten'=>['office_projects'=>['id'],'office_project_buildings'=>['project_id','building_id'],'office_project_memberships'=>['project_id','operator_id','permission']],
 'Gebouwen en woningtypen'=>['office_buildings'=>['id','building_name'],'office_housing_types'=>['id','building_id','code','name'],'office_project_housing_types'=>['project_id','building_id','housing_type_id','dwelling_count']],
 'CRM en relaties'=>['office_crm_relations'=>['id','display_name','status'],'office_crm_relation_roles'=>['relation_id','role_code']],
 'Intake en leads'=>['office_crm_leads'=>[]],
 'Deals'=>['office_crm_opportunities'=>['id','relation_id','title','revision','stage','status'],'office_crm_deal_buildings'=>[]],
 'Calculatie'=>['office_calc_crm_drafts'=>['id','created_by','opportunity_id','calculation_id','crm_revision','project_id'],'calculations'=>['id','office_calculation_id'],'calculation_versions'=>['id','calculation_id','version_no'],'calculation_lines'=>['version_id','quantity','calc_net_quantity','calc_material_loss_percent','calc_extra_labor_hours','calc_quantity_source','calc_quantity_source_ref']],
 'Werkbegroting en overdracht'=>['office_calc_crm_acceptance_transfers'=>['crm_opportunity_id','calculation_id','accepted_revision','office_project_id'],'office_calc_work_budget_lines'=>[]],
 'Inkoop'=>['office_procurement_orders'=>['project_id','building_id']],
 'Kennis en kwaliteit'=>['office_quality_checks'=>['project_id','building_id'],'office_risks'=>['project_id','building_id']]];
 $dependencies=[
 'Deals'=>['CRM en relaties','Gebouwen en woningtypen'],
 'Calculatie'=>['Deals','Gebouwen en woningtypen'],
 'Werkbegroting en overdracht'=>['Calculatie','Projecten'],
 'Planning en inzet'=>['Projecten'],
 'OnSite'=>['Planning en inzet','Gebouwen en woningtypen'],
 'Urencontrole'=>['OnSite'],
 'Inkoop'=>['Projecten','Werkbegroting en overdracht'],
 'Kennis en kwaliteit'=>['Projecten','Gebouwen en woningtypen'],
 'FinanceCenter'=>['Projecten','Werkbegroting en overdracht']];
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
 $columnQuery=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 foreach ($schemas[$selected]??[] as $table=>$columns) {
 $columnQuery->execute([$table]);
 $available=$columnQuery->fetchAll(PDO::FETCH_COLUMN);
 $missing=array_diff($columns,$available);
 $status=!$available?'TABEL NIET ZICHTBAAR':(!$columns?'TABEL AANWEZIG; KOLOMMEN NIET GECONTROLEERD':($missing?'ONTBREKENDE KOLOMMEN: '.implode(', ',$missing):'VEREISTE KOLOMMEN AANWEZIG'));
 $lines[]='Schema '.$table.': '.$status;
 $html.='<tr><td>'.$e('Schema '.$table).'</td><td>'.$e($status).'</td></tr>';
 }
 foreach ($dependencies[$selected]??[] as $dependency) {
 [$depFlags,$depTables]=$groups[$dependency];
 $depTables=array_unique(array_merge($depTables,array_keys($schemas[$dependency]??[])));
 $issues=[];
 foreach ($depTables as $table) {$q->execute([$table]);if ((int)$q->fetchColumn()!==1) {$issues[]=$table.' ontbreekt';}}
 foreach ($depFlags as $flag) {if (getenv($flag)!=='enabled') {$issues[]=$flag.' niet actief';}}
 $status=$issues?implode('; ',$issues):(($depTables||$depFlags)?'Geregistreerde tabel- en flagcontroles akkoord':'Geen controles geregistreerd');
 $lines[]='Afhankelijkheid '.$dependency.': '.$status.' (geen functionele ketentest)';
 $html.='<tr><td>'.$e('Afhankelijkheid '.$dependency).'</td><td>'.$e($status).'</td></tr>';
 }
 if (!$flags && !$tables && empty($schemas[$selected])) {$lines[]='Geen specifieke technische controles geregistreerd.';}
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
 $rightsSchemas=[
 'CRM en relaties'=>['office_operator_accounts'=>['id','active','can_edit_crm']],
 'Deals'=>['office_operator_accounts'=>['id','active','can_edit_crm']],
 'Calculatie'=>['office_operator_accounts'=>['id','active','can_edit_crm'],'office_calc_crm_drafts'=>['created_by','opportunity_id']],
 'Projecten'=>['office_project_memberships'=>['project_id','operator_id','permission']],
 'Gebouwen en woningtypen'=>['office_project_memberships'=>['project_id','operator_id','permission'],'office_building_register_grants'=>['building_id','operator_id']],
 'Inkoop'=>['office_project_memberships'=>['project_id','operator_id','permission']],
 'Kennis en kwaliteit'=>['office_project_memberships'=>['project_id','operator_id','permission']]];
 $lines[]='Toegang tot diagnose: sitebeheerder bevestigd.';
 foreach ($rightsSchemas[$selected]??[] as $table=>$columns) {
 $columnQuery->execute([$table]);
 $missing=array_diff($columns,$columnQuery->fetchAll(PDO::FETCH_COLUMN));
 $lines[]='Rechtenstructuur '.$table.': '.($missing?'ONTBREKEND: '.implode(', ',$missing):'VEREISTE KOLOMMEN AANWEZIG');
 }
 if (!isset($rightsSchemas[$selected])) {$lines[]='Rechtenstructuur: geen specifieke controle geregistreerd.';}
 $lines[]='Modulerechten, koppelingen en achtergrondprocessen: niet gecontroleerd.';
 $lines[]='Rechtenstructuur is geen bewijs van effectieve autorisatie; achtergrondprocessen vereisen afzonderlijke runtimecontrole.';
 $prompt="Analyseer uitsluitend deze module. Controleer eerst de actuele repository en actieve testrelease. Verifieer afwijkingen en ontbrekende controles; een uitgeschakelde optionele flag is niet automatisch een fout. Hergebruik bestaande functionaliteit. Herstel uitsluitend bewezen problemen via een afzonderlijke Git-branch, voeg regressietesten toe en rapporteer blokkades. Wijzig productie niet. Vraag geen geheimen op.\n\n".implode("\n",$lines);
 $html.='</table><p>'.$e(implode(' ',array_slice($lines,-2))).'</p><h3>AI-herstelprompt</h3><p>Selecteer de volledige tekst en kopieer deze naar ChatGPT of Codex.</p><button type="button" id="copy-prompt">Kopieer prompt</button><span id="copy-status" role="status"></span><textarea id="repair-prompt" readonly rows="18" aria-label="AI-herstelprompt">'.$e($prompt).'</textarea><p><a href="?module='.rawurlencode($selected).'">Diagnose opnieuw uitvoeren</a></p></section>';
 }
 echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Systeemdiagnostiek</title><style>body{font:15px system-ui;margin:24px;color:#24364a}section{border:1px solid #ccd5df;padding:16px;margin:12px 0}td{padding:6px;overflow-wrap:anywhere}table{width:100%;table-layout:fixed}nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px}.module{padding:12px;border:1px solid #ccd5df;border-radius:6px}textarea{box-sizing:border-box;width:100%;font:14px monospace;padding:12px}</style><a href="/office-test/instellingen">Instellingen</a><h1>Systeemdiagnostiek — Office</h1><p>Alleen lezen. Selecteer een module voor actuele technische controles en een AI-herstelprompt.</p>'.$html.'<script nonce="'.$e($nonce).'">const b=document.getElementById("copy-prompt");if(b)b.addEventListener("click",async()=>{const t=document.getElementById("repair-prompt"),s=document.getElementById("copy-status");try{await navigator.clipboard.writeText(t.value);s.textContent=" Gekopieerd";}catch(e){t.focus();t.select();s.textContent=" Tekst geselecteerd; kopieer met Ctrl+C.";}});</script></html>';
} catch (Throwable $e) {http_response_code(503);exit('Diagnostiek tijdelijk niet beschikbaar.');}
