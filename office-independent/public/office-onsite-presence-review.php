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
   $minutesRaw=$_POST['approved_minutes']??'';
   $correctedSeconds=null;
   if ($minutesRaw!=='' && $minutesRaw!==null) {
     if (!is_string($minutesRaw) || !preg_match('/^[1-9][0-9]{0,3}$/D',$minutesRaw)
         || (int)$minutesRaw>1440) {http_response_code(400);exit('Ongeldige goedgekeurde minuten.');}
     $correctedSeconds=(int)$minutesRaw*60;
   }
   $action=$_POST['review_action']??'approve';
   $service=new \Brebo\Office\Inzet\OfficeOnSiteApprovalService($db);
   if ($action==='reject') {
     $reason=$_POST['rejection_reason']??null;
     if (!is_string($reason) || trim($reason)==='' || mb_strlen(trim($reason),'UTF-8')>1000) {
       http_response_code(400);exit('Afkeuren vereist een opmerking van maximaal 1000 tekens.');
     }
     $service->reject($id,$in,$out,$operator->operatorId,$reason);
     header('Location: ?id='.$id.'&rejected=1',true,303);
   } elseif ($action==='approve') {
     $service->approve($id,$in,$out,$operator->operatorId,$correctedSeconds);
     header('Location: ?id='.$id.'&approved=1',true,303);
   } else {http_response_code(400);exit('Ongeldige beoordelingsactie.');}
   exit;
 }
 // Select the entire historical evidence stream up to the week's end.
 $weekRaw=$_GET['week']??date('o-\\WW');
 if (!is_string($weekRaw) || !preg_match('/^(20[0-9]{2})-W(0[1-9]|[1-4][0-9]|5[0-3])$/D',$weekRaw,$weekMatch)) {
   $weekRaw=date('o-\\WW');
   preg_match('/^(20[0-9]{2})-W(0[1-9]|[1-4][0-9]|5[0-3])$/D',$weekRaw,$weekMatch);
 }
 $weekStart=(new DateTimeImmutable('now',new DateTimeZone('Europe/Brussels')))
   ->setISODate((int)$weekMatch[1],(int)$weekMatch[2])->setTime(0,0);
 $weekEnd=$weekStart->modify('+7 days');
 $review=(new \Brebo\Office\Inzet\OnSitePresenceReviewRepository($db))
   ->forManagerWeek($operator->operatorId,(string)$id,$weekEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
 // Show persisted review state: previously handled intervals must not be offered again.
 $reviewedPairs=[];
 $reasonsEnabled=getenv('OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED')==='enabled';
 $decisions=$db->prepare($reasonsEnabled
   ? 'SELECT d.in_event_id,d.out_event_id,d.status,d.reviewed_seconds,r.reason FROM office_onsite_hours_decisions d LEFT JOIN office_onsite_rejection_reasons r ON r.decision_reference=d.decision_reference WHERE d.project_id=?'
   : 'SELECT in_event_id,out_event_id,status,reviewed_seconds FROM office_onsite_hours_decisions WHERE project_id=?');
 $decisions->execute([$id]);
 foreach ($decisions->fetchAll(PDO::FETCH_ASSOC) as $decisionRow) {
   $reviewedPairs[(string)$decisionRow['in_event_id'].'|'.(string)$decisionRow['out_event_id']]=$decisionRow;
 }
 $review['intervals']=array_map(static function(array $interval) use ($reviewedPairs):array {
   $key=(string)($interval['arrival_event_id']??'').'|'.(string)($interval['departure_event_id']??'');
   if (isset($reviewedPairs[$key])) {
     $interval['review_status']=(string)$reviewedPairs[$key]['status'];
     $interval['reviewed_seconds']=(int)$reviewedPairs[$key]['reviewed_seconds'];
     if (isset($reviewedPairs[$key]['reason'])) $interval['review_reason']=(string)$reviewedPairs[$key]['reason'];
   }
   return $interval;
 },$review['intervals']??[]);
 // Keep the displayed review scoped to the selected week; old events were used for pairing only.
 $review['intervals']=array_values(array_filter($review['intervals'],static function(array $interval) use ($weekStart,$weekEnd):bool {
   $start=$interval['entered_at']??null;
   if (!is_string($start)) return false;
   $local=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Brussels'));
   return $local>=$weekStart && $local<$weekEnd;
 }));
 $weekly=[];
 foreach ($review['intervals'] as $interval) {
   $start=$interval['entered_at']??null;
   if (!is_string($start)) continue;
   try {$local=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Brussels'));}
   catch (Throwable $ignored) {continue;}
   if ($local<$weekStart || $local>=$weekEnd) continue;
   $person=(string)($interval['person_id']??'');
   if ($person==='') continue;
   if (!isset($weekly[$person])) $weekly[$person]=['registered'=>0,'approved'=>0,'rejected'=>0,'pending'=>0];
   $duration=max(0,(int)($interval['elapsed_seconds']??0));
   $weekly[$person]['registered']+=$duration;
   $status=$interval['review_status']??'pending';
   if ($status==='approved') $weekly[$person]['approved']+=(int)($interval['reviewed_seconds']??0);
   elseif ($status==='rejected') $weekly[$person]['rejected']+=$duration;
   else $weekly[$person]['pending']+=$duration;
 }
 ksort($weekly,SORT_NATURAL);
} catch (Throwable $error) {
 http_response_code(503);exit('Aanwezigheidscontrole tijdelijk niet beschikbaar.');
}
$escape=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
 .'<title>Aanwezigheidscontrole · BREBO Office</title><style>body{font:16px system-ui;margin:2rem auto;max-width:1100px;padding:1rem;color:#243244}'
 .'table{width:100%;border-collapse:collapse;margin-bottom:2rem}th,td{padding:.7rem;border-bottom:1px solid #ccd5df;text-align:left}'
 .'section{padding:1rem}a{color:#245b8a}</style></head><body><main>'
 .'<p><a href="/office-test/projecten/detail?id='.$id.'&amp;tab=arbeid-inzet">← Arbeid &amp; Inzet · project '.$escape($id).'</a></p>'
 .'<h1>Inzet · project '.$escape($id).'</h1>'
 .'<h2>Weekoverzicht</h2><form method="get"><input type="hidden" name="id" value="'.$escape($id).'">'
 .'<label>Week <input type="week" name="week" value="'.$escape($weekRaw).'"></label> <button type="submit">Toon week</button></form>'
 .'<p>Week vanaf '.$escape($weekStart->format('d-m-Y')).' · overzicht van de geladen aanwezigheidsintervallen</p>'
 .'<table><thead><tr><th>Medewerker</th><th>Geregistreerd</th><th>Goedgekeurd</th><th>Afgekeurd</th><th>Te beoordelen</th></tr></thead><tbody>'
 .implode('',array_map(static function(string $person,array $values) use ($escape):string {
   $format=static fn(int $seconds):string=>sprintf('%d:%02d',intdiv($seconds,3600),intdiv($seconds%3600,60));
   return '<tr><td>'.$escape($person).'</td><td>'.$format($values['registered']).'</td><td>'.$format($values['approved']).'</td><td>'.$format($values['rejected']).'</td><td>'.$format($values['pending']).'</td></tr>';
 },array_keys($weekly),array_values($weekly)))
 .'</tbody></table>'
 .\Brebo\Office\Inzet\OnSitePresenceReviewView::render($review)
 .($approvalsEnabled ? '<h2>Uren goedkeuren</h2><p>Alleen volledige OnSite-registraties. Goedkeuring boekt de uren direct op het project.</p>'
 .(isset($_GET['approved'])?'<p role="status">Uren goedgekeurd en geboekt.</p>':'')
 .(isset($_GET['rejected'])?'<p role="status">Registratie afgekeurd; geen uren geboekt.</p>':'')
 .implode('',array_map(static function(array $interval) use ($escape,$id):string {
   if (isset($interval['review_status']) || ($interval['elapsed_seconds']??0)>86400 || ($interval['elapsed_seconds']??0)<1) return '';
   return '<form method="post" action=""><input type="hidden" name="id" value="'.$escape($id).'">'
     .'<input type="hidden" name="csrf" value="'.$escape($_SESSION['office_onsite_review_csrf']).'">'
     .'<input type="hidden" name="in_event_id" value="'.$escape($interval['arrival_event_id']).'">'
     .'<input type="hidden" name="out_event_id" value="'.$escape($interval['departure_event_id']).'">'
     .'<span>Medewerker '.$escape($interval['person_id']).' · '. $escape(round($interval['elapsed_seconds']/3600,2)).' uur</span> '
     .'<label>Goedgekeurde minuten (leeg = volledig): <input type="number" name="approved_minutes" min="1" max="'.(int)floor($interval['elapsed_seconds']/60).'" step="1" placeholder="Volledig"></label> '
     .'<button type="submit" name="review_action" value="approve">Goedkeuren en boeken</button> '
     .(getenv('OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED')==='enabled'
       ? '<label>Reden afkeuring: <input type="text" name="rejection_reason" maxlength="1000"></label> '
         .'<button type="submit" name="review_action" value="reject">Afkeuren</button>' : '')
     .'</form>';
 },$review['intervals']??[])) : '')
 .'</main><script nonce="'.$escape($nonce).'">'
 .'function updateTimers(){for(const el of document.querySelectorAll(".onsite-live-duration")){const start=Number(el.dataset.start);if(!Number.isFinite(start)||start<=0)continue;const seconds=Math.max(0,Math.floor(Date.now()/1000-start));const h=Math.floor(seconds/3600);const m=Math.floor(seconds%3600/60);const s=seconds%60;el.textContent=[h,m,s].map(n=>String(n).padStart(2,"0")).join(":");}}'
 .'updateTimers();setInterval(updateTimers,1000);setInterval(()=>{if(!document.hidden)location.reload();},30000);'
 .'</script></body></html>';
