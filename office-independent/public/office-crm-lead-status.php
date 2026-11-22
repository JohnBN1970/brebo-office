<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
foreach (['OFFICE_TEST_CRM_ENABLED','OFFICE_CRM_LEADS_SCHEMA_VERIFIED','OFFICE_CRM_LEADS_WRITE_ENABLED'] as $flag) {
 if (getenv($flag)!=='enabled') { http_response_code(503); exit('Leadbeheer niet beschikbaar.'); }
}
if (getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'||getenv('OFFICE_LOGIN_ENABLED')!=='enabled') { http_response_code(403); exit; }
$host=$_SERVER['HTTP_HOST']??'';$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!hash_equals($allowed,(string)$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
 foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
 $stmt=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$operator->operatorId]);
 if (!$stmt->fetchColumn()) { http_response_code(403); exit; }
 $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if (!is_int($id)) { http_response_code(400); exit('Ongeldig leadnummer.'); }
 $stmt=$db->prepare('SELECT id,title,status,converted_opportunity_id FROM office_crm_leads WHERE id=?');$stmt->execute([$id]);$lead=$stmt->fetch(PDO::FETCH_ASSOC);
 if (!$lead) { http_response_code(404); exit('Lead niet gevonden.'); }
} catch(Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('BREBO_CRM_LEAD_STATUS');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_lead_status_csrf'])) $_SESSION['crm_lead_status_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';
$action=$_SERVER['REQUEST_METHOD']==='POST'?($_POST['action']??''):($_GET['action']??'');
if (!is_string($action)||!in_array($action,['archive','restore'],true)) { http_response_code(400); exit('Ongeldige actie.'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['crm_lead_status_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
 $reason=is_string($_POST['reason']??null)?trim($_POST['reason']):'';
 if ($reason===''||mb_strlen($reason)>500) $error='Een reden is verplicht (maximaal 500 tekens).';
 if (!$error) {
  try {
   $db->beginTransaction();
   $stmt=$db->prepare('SELECT status,converted_opportunity_id FROM office_crm_leads WHERE id=? FOR UPDATE');$stmt->execute([$id]);$current=$stmt->fetch(PDO::FETCH_ASSOC);
   $expected=$action==='archive'?'active':'archived';
   $next=$action==='archive'?'archived':'active';
   if (!$current||$current['status']!==$expected||$current['converted_opportunity_id']!==null) throw new RuntimeException('De leadstatus is gewijzigd of de lead is al omgezet naar een deal.');
   $stmt=$db->prepare('UPDATE office_crm_leads SET status=? WHERE id=?');$stmt->execute([$next,$id]);
   $stmt=$db->prepare('INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,note) VALUES (?,?,?,?)');
   $stmt->execute([$id,(string)$operator->operatorId,$action,json_encode(['reason'=>$reason,'old_status'=>$expected,'new_status'=>$next],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   $db->commit();$_SESSION['crm_lead_status_csrf']=bin2hex(random_bytes(32));
   header('Location: /office-test/crm/aanvragen/detail?id='.$id,true,303);exit;
  } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack();$error=$e instanceof RuntimeException?$e->getMessage():'Status wijzigen mislukt.'; }
 }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Leadstatus beheren</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a}main{max-width:650px;margin:36px auto;background:white;padding:26px;border-radius:12px}label{display:block;margin:20px 0}textarea{display:block;width:100%;box-sizing:border-box}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}</style></head><body><main><a href="/office-test/crm/aanvragen/detail?id='.$id.'">Terug naar lead</a><h1>'.($action==='archive'?'Lead archiveren':'Lead herstellen').'</h1><p>'.$h($lead['title']).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
if ($lead['converted_opportunity_id']!==null) echo '<p>Deze lead is omgezet naar een deal. Statuswijziging geblokkeerd.</p>';
elseif (($action==='archive'&&$lead['status']!=='active')||($action==='restore'&&$lead['status']!=='archived')) echo '<p>Deze actie past niet bij de huidige leadstatus.</p>';
else echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_lead_status_csrf']).'"><input type="hidden" name="action" value="'.$h($action).'"><label>Reden *<textarea name="reason" maxlength="500" required></textarea></label><button type="submit">'.($action==='archive'?'Archiveren bevestigen':'Herstellen bevestigen').'</button></form>';
echo '</main></body></html>';
