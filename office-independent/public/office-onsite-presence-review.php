<?php
declare(strict_types=1);
// Office-test OnSite review. Approval writes require a separate explicit feature flag.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$nonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-".$nonce."'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) {http_response_code(405);header('Allow: GET, POST');exit;}
$approvalsEnabled=getenv('OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED')==='enabled';
if ($method==='POST' && !$approvalsEnabled) {http_response_code(403);exit('Uren goedkeuren is niet geactiveerd.');}
if ($approvalsEnabled) {
 if (session_status()!==PHP_SESSION_ACTIVE) session_start(['cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
 if (empty($_SESSION['office_onsite_review_csrf'])) $_SESSION['office_onsite_review_csrf']=bin2hex(random_bytes(32));
}
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
 || getenv('OFFICE_NATIVE_PROJECTS_ENABLED')!=='enabled'
 || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')!=='enabled'
 || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED')!=='enabled'
 || getenv('OFFICE_TEST_PRESENCE_REVIEW_ENABLED')!=='enabled') {
 http_response_code(503);exit('Aanwezigheidscontrole niet beschikbaar.');
}
$host=$_SERVER['HTTP_HOST']??'';
if (!is_string($host) || $host==='' || $host!==getenv('OFFICE_PUBLIC_HOST')
 || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {
 http_response_code(403);exit;
}
$id=filter_var(($method==='POST'?($_POST['id']??null):($_GET['id']??null)),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!is_int($id)) {http_response_code(400);exit('Ongeldig projectnummer.');}
try {
 foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
   'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
   require_once dirname(__DIR__).'/src/Intake/'.$file;
 foreach (['OnSiteOfficeZoneProjectAccess.php','OnSitePresenceIntervalProjector.php',
   'OnSitePresenceReviewRepository.php','OnSitePresenceReviewView.php'] as $file)
   require_once dirname(__DIR__).'/src/Inzet/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',
   getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
   [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
   new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if ($operator===null) {header('Location: /office-test/inloggen',true,302);exit;}
 if (!(new \Brebo\Office\Inzet\OnSiteOfficeZoneProjectAccess($db))->canManage($operator->operatorId,$id)) {
   http_response_code(404);exit('Project niet beschikbaar.');
 }
 if ($method==='POST') {
   $token=$_POST['csrf']??null;
   if (!is_string($token) || !hash_equals((string)$_SESSION['office_onsite_review_csrf'],$token)) {
     http_response_code(403);exit('Ongeldige beveiligingscode.');
   }
   $origin=$_SERVER['HTTP_ORIGIN']??'';
   if ($origin!=='' && $origin!=='https://'.$host) {http_response_code(403);exit('Ongeldige herkomst.');}
   require_once dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php';
   $in=$_POST['in_event_id']??null;$out=$_POST['out_event_id']??null;
   if (!is_string($in) || !is_string($out)) {http_response_code(400);exit('Ongeldige registratie.');}
   (new \Brebo\Office\Inzet\OfficeOnSiteApprovalService($db))->approve($id,$in,$out,$operator->operatorId);
   header('Location: ?id='.$id.'&approved=1',true,303);
   exit;
 }
 $review=(new \Brebo\Office\Inzet\OnSitePresenceReviewRepository($db))
   ->forManager($operator->operatorId,(string)$id);
} catch (Throwable $error) {
 http_response_code(503);exit('Aanwezigheidscontrole tijdelijk niet beschikbaar.');
}
$escape=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
 .'<title>Aanwezigheidscontrole · BREBO Office</title><style>body{font:16px system-ui;margin:2rem auto;max-width:1100px;padding:1rem;color:#243244}'
 .'table{width:100%;border-collapse:collapse;margin-bottom:2rem}th,td{padding:.7rem;border-bottom:1px solid #ccd5df;text-align:left}'
 .'section{padding:1rem}a{color:#245b8a}</style></head><body><main>'
 .'<p><a href="/office-test/projecten/detail?id='.$id.'">← Project '.$escape($id).'</a></p>'
 .'<h1>Inzet · project '.$escape($id).'</h1>'
 .\Brebo\Office\Inzet\OnSitePresenceReviewView::render($review)
 .($approvalsEnabled ? '<h2>Uren goedkeuren</h2><p>Alleen volledige OnSite-registraties. Goedkeuring boekt de uren direct op het project.</p>'
 .(isset($_GET['approved'])?'<p role="status">Uren goedgekeurd en geboekt.</p>':'')
 .implode('',array_map(static function(array $interval) use ($escape,$id):string {
   if (($interval['elapsed_seconds']??0)>86400 || ($interval['elapsed_seconds']??0)<1) return '';
   return '<form method="post" action=""><input type="hidden" name="id" value="'.$escape($id).'">'
     .'<input type="hidden" name="csrf" value="'.$escape($_SESSION['office_onsite_review_csrf']).'">'
     .'<input type="hidden" name="in_event_id" value="'.$escape($interval['arrival_event_id']).'">'
     .'<input type="hidden" name="out_event_id" value="'.$escape($interval['departure_event_id']).'">'
     .'<span>Medewerker '.$escape($interval['person_id']).' · '. $escape(round($interval['elapsed_seconds']/3600,2)).' uur</span> '
     .'<button type="submit">Goedkeuren en boeken</button></form>';
 },$review['intervals']??[])) : '')
 .'</main><script nonce="'.$escape($nonce).'">'
 .'function updateTimers(){for(const el of document.querySelectorAll(".onsite-live-duration")){const start=Number(el.dataset.start);if(!Number.isFinite(start)||start<=0)continue;const seconds=Math.max(0,Math.floor(Date.now()/1000-start));const h=Math.floor(seconds/3600);const m=Math.floor(seconds%3600/60);const s=seconds%60;el.textContent=[h,m,s].map(n=>String(n).padStart(2,"0")).join(":");}}'
 .'updateTimers();setInterval(updateTimers,1000);setInterval(()=>{if(!document.hidden)location.reload();},30000);'
 .'</script></body></html>';
