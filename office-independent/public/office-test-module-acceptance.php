<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$nonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit;}
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

 require_once dirname(__DIR__).'/src/Approval/OfficeTestModuleAcceptanceRepository.php';
 $catalog=require dirname(__DIR__).'/src/Approval/office-test-module-acceptance-catalog.php';
 $release=basename(dirname(__DIR__));
 if (!preg_match('/\A[0-9a-f]{40}\z/D',$release)) {http_response_code(503);exit('Release niet vastgesteld.');}
 session_start();
 $_SESSION['module_acceptance_csrf']??=bin2hex(random_bytes(32));
 $csrf=$_SESSION['module_acceptance_csrf'];
 $module=$_GET['module']??array_key_first($catalog);
 if (!is_string($module)||!isset($catalog[$module])) {http_response_code(400);exit;}
 $repo=new \Brebo\Office\Approval\OfficeTestModuleAcceptanceRepository($db);
 if ($_SERVER['REQUEST_METHOD']==='POST') {
 $token=$_POST['csrf']??null;
 $test=$_POST['test']??null;
 $status=isset($_POST['checkbox_action'])?(isset($_POST['accepted'])?'passed':'pending'):($_POST['quick_status']??$_POST['status']??null);
 $notes=$_POST['notes']??'';
 if (!is_string($token)||!hash_equals($csrf,$token)||!is_string($test)||!isset($catalog[$module][$test])||!is_string($status)||!is_string($notes)) {http_response_code(400);exit('Ongeldige invoer.');}
 try {$repo->record($module,$test,$release,$status,$notes,(int)$operator->operatorId);}
 catch (InvalidArgumentException $ex) {http_response_code(400);exit('Ongeldig resultaat; afkeuren vereist een toelichting.');}
 header('Location: ?module='.rawurlencode($module),true,303);exit;
 }
 $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 [$groups,$routes,$schemas,$dependencies]=require dirname(__DIR__).'/src/Approval/office-test-diagnostic-profiles.php';
 $columnQuery=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 $router=file_get_contents(__DIR__.'/index.php');
 $technical=[];
 foreach ($groups as $name=>[$flags,$tables]) {
 $ok=0;$blocked=0;$attention=0;
 foreach ($flags as $flag) {if(getenv($flag)==='enabled'){$ok++;}else{$attention++;}}
 foreach (array_unique(array_merge($tables,array_keys($schemas[$name]??[]))) as $table) {
 $columnQuery->execute([$table]);$available=$columnQuery->fetchAll(PDO::FETCH_COLUMN);
 if (!$available || array_diff($schemas[$name][$table]??[],$available)) {$blocked++;}else{$ok++;}
 }
 if(isset($routes[$name])) {[$route,$handler]=$routes[$name];if(is_string($router)&&str_contains($router,"'".$route."' => '".$handler."'")&&is_file(__DIR__.'/'.$handler)){$ok++;}else{$attention++;}}
 foreach ($dependencies[$name]??[] as $dependency) {
 [$depFlags,$depTables]=$groups[$dependency];
 foreach ($depFlags as $flag) {if(getenv($flag)==='enabled'){$ok++;}else{$attention++;}}
 foreach (array_unique(array_merge($depTables,array_keys($schemas[$dependency]??[]))) as $table) {
 $columnQuery->execute([$table]);$available=$columnQuery->fetchAll(PDO::FETCH_COLUMN);
 if(!$available||array_diff($schemas[$dependency][$table]??[],$available)){$blocked++;}else{$ok++;}
 }
 }
 if(in_array($name,['E-mail','Documenten'],true)){
 $file='/run/brebo-office-test/diagnostics/mail-workers.json';
 $raw=is_readable($file)?file_get_contents($file,false,null,0,8193):false;
 $snapshot=is_string($raw)&&strlen($raw)<=8192?json_decode($raw,true):null;
 $stamp=$snapshot['checked_at']??null;
 if(!is_int($stamp)||$stamp>time()||$stamp<time()-300){$attention++;}
 else{foreach(['brebo-office-test-mail-import.service','brebo-office-test-mail-attachments.service'] as $unit){
 $state=$snapshot['units'][$unit]??null;
 if($state==='failed'){$blocked++;}elseif(in_array($state,['active','inactive'],true)){$ok++;}else{$attention++;}
 }}
 }
 $technical[$name]=[$blocked?'Blokkade':($attention?'Aandacht':($ok?'Deelcontroles akkoord':'Niet gecontroleerd')),$ok,$blocked,$attention];
 }
 $totals=['pending'=>0,'passed'=>0,'failed'=>0,'retest'=>0];
 $html='<table><thead><tr><th>Module</th><th>Actuele technische status</th><th>Goedgekeurd</th><th>Afgekeurd</th><th>Hertest</th><th>Nog te testen</th><th>Acties</th></tr></thead><tbody>';
 foreach ($catalog as $name=>$tests) {
 $counts=array_fill_keys(array_keys($totals),0);
 foreach ($tests as $key=>$label) {
 $result=$repo->latest($name,$key,$release);
 $state=$result['status']??'pending';
 $counts[$state]++;$totals[$state]++;
 }
 $tone=$technical[$name][2]?'blocked':($technical[$name][3]?'attention':($technical[$name][1]?'ok':'unknown'));
 $display=$name==='Oplevering en snagging'?'Oplevering en restpunten':$name;
 $html.='<tr><td><a href="?module='.rawurlencode($name).'">'.$e($display).'</a></td><td><strong class="'.$tone.'">'.$e($technical[$name][0]).'</strong><small>'.$technical[$name][1].' OK / '.$technical[$name][2].' blokkades / '.$technical[$name][3].' aandacht</small></td><td>'.$counts['passed'].' / '.count($tests).'<progress max="'.count($tests).'" value="'.$counts['passed'].'"></progress></td><td>'.$counts['failed'].'</td><td>'.$counts['retest'].'</td><td>'.$counts['pending'].'</td><td><a href="/office-test/instellingen/systeemdiagnostiek?module='.rawurlencode($name).'">Diagnose en herstelprompt</a></td></tr>';
 }
 $html.='</tbody></table><h2>'.$e($module==='Oplevering en snagging'?'Oplevering en restpunten':$module).'</h2>';
 $total=array_sum($totals);
 $summary='<h2>Totale voortgang</h2><p>'.$totals['passed'].' / '.$total.' goedgekeurd — '.$totals['failed'].' afgekeurd — '.$totals['retest'].' hertest — '.$totals['pending'].' nog te testen</p><progress max="'.$total.'" value="'.$totals['passed'].'"></progress>';
 $summary.='<div class="kpis">';
 foreach (['passed'=>'Goedgekeurd','failed'=>'Afgekeurd','retest'=>'Hertest','pending'=>'Nog te testen'] as $state=>$label) {$summary.='<div><small>'.$label.'</small><strong>'.$totals[$state].'</strong></div>';}
 $summary.='</div>';
 $passed=0;
 foreach ($catalog[$module] as $key=>$label) {
 $row=$repo->latest($module,$key,$release);
 $status=$row['status']??'pending';
 if ($status==='passed') {$passed++;}
 $html.='<form method="post"><h3>'.$e($label).'</h3><input type="hidden" name="csrf" value="'.$e($csrf).'"><input type="hidden" name="test" value="'.$e($key).'"><label><input type="checkbox" name="accepted" value="1" class="accept-check"'.($status==='passed'?' checked':'').'> Functioneel goedgekeurd</label><button type="submit" name="checkbox_action" value="1" class="check-save">Vinkje opslaan</button><select name="status">';
 foreach (['pending'=>'Nog te testen','passed'=>'Goedgekeurd','failed'=>'Afgekeurd','retest'=>'Hertest vereist'] as $value=>$title) {$html.='<option value="'.$value.'"'.($status===$value?' selected':'').'>'.$title.'</option>';}
 $html.='</select><textarea name="notes" maxlength="8000" placeholder="Toelichting">'.$e($row['notes']??'').'</textarea><button type="submit">Status en toelichting opslaan</button></form>';
 }
 echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Test- en acceptatiecentrum</title><style>body{font:15px system-ui;margin:24px;color:#24364a;background:#f6f8fb}table,form{background:white}small{display:block;color:#526477}.ok{color:#18733b}.blocked{color:#b42318}.attention{color:#946200}.unknown{color:#526477}.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:16px 0}.kpis div{background:white;border:1px solid #ccd5df;border-radius:8px;padding:16px}.kpis strong{display:block;font-size:28px}nav{display:flex;flex-wrap:wrap;gap:12px}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:8px;border-bottom:1px solid #ddd}progress{width:100%}form{border:1px solid #ccc;padding:12px;margin:8px 0}textarea{display:block;width:95%;margin:8px 0}</style><h1>Test- en acceptatiecentrum</h1><p>Office-test — '.$e($release).' — controle UTC '.$e(gmdate('c')).'</p><p>Technische deelcontroles: flags, tabellen, kolommen, statische routes, afhankelijkheden en beschikbare workerstatus; geen bewijs van functionele werking.</p><p>'.$passed.' / '.count($catalog[$module]).' goedgekeurd</p>'.$summary.$html.'<script nonce="'.$e($nonce).'">document.querySelectorAll(".accept-check").forEach(c=>c.addEventListener("change",()=>c.form.requestSubmit(c.form.querySelector(".check-save"))));</script></html>';
} catch (Throwable $ex) {http_response_code(503);exit('Acceptatiecentrum tijdelijk niet beschikbaar.');}
