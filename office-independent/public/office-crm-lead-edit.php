<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only' || getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'
    || getenv('OFFICE_LOGIN_ENABLED')!=='enabled' || getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED')!=='enabled') {
    http_response_code(503); exit('Leadbeheer niet beschikbaar.');
}
$host=$_SERVER['HTTP_HOST']??'';$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!hash_equals($allowed,(string)$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
 foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
 $stmt=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$operator->operatorId]);
 if (!$stmt->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
 $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if (!is_int($id)) { http_response_code(400); exit('Ongeldig leadnummer.'); }
 $stmt=$db->prepare('SELECT id,title,source_detail,campaign,contact_name,contact_email,description,owner_operator_id,updated_at,converted_opportunity_id,status FROM office_crm_leads WHERE id=?');
 $stmt->execute([$id]);$lead=$stmt->fetch(PDO::FETCH_ASSOC);
 if (!$lead) { http_response_code(404); exit('Lead niet gevonden.'); }
} catch(Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (getenv('OFFICE_CRM_LEADS_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Leadbewerking niet vrijgegeven.'); }
if ($lead['status']!=='active'||$lead['converted_opportunity_id']!==null) { http_response_code(409); exit('Deze lead is niet bewerkbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('BREBO_CRM_LEAD_EDIT');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_lead_edit_csrf'])) $_SESSION['crm_lead_edit_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$fields=['title'=>255,'source_detail'=>255,'campaign'=>255,'contact_name'=>255,'contact_email'=>254,'description'=>10000];
$labels=['title'=>'Aanvraag','source_detail'=>'Specificatie bron','campaign'=>'Campagne','contact_name'=>'Contactpersoon','contact_email'=>'E-mailadres','description'=>'Omschrijving'];
$error='';
if ($method==='POST') {
 if (!is_string($_POST['csrf']??null)||!hash_equals((string)$_SESSION['crm_lead_edit_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
 $values=[];
 foreach ($fields as $key=>$max) {
  if (!is_string($_POST[$key]??null)||mb_strlen(trim($_POST[$key]),'UTF-8')>$max) { $error='Ongeldige invoer.';break; }
  $values[$key]=trim($_POST[$key]);
 }
 if (!$error && ($values['title']===''||($values['contact_email']!==''&&!filter_var($values['contact_email'],FILTER_VALIDATE_EMAIL)))) $error='Controleer titel en e-mailadres.';
 $reason=trim(is_string($_POST['reason']??null)?$_POST['reason']:'');
 if (!$error && ($reason===''||mb_strlen($reason)>500)) $error='Geef een wijzigingsreden op (maximaal 500 tekens).';
 $expected=$_POST['expected_updated_at']??null;
 if (!$error && (!is_string($expected)||!hash_equals((string)$lead['updated_at'],$expected))) { http_response_code(409); exit('De lead is intussen gewijzigd. Ververs de pagina.'); }
 if (!$error) {
  try {
   $db->beginTransaction();
   $stmt=$db->prepare('SELECT * FROM office_crm_leads WHERE id=? FOR UPDATE');$stmt->execute([$id]);$current=$stmt->fetch(PDO::FETCH_ASSOC);
   if (!$current||$current['status']!=='active'||$current['converted_opportunity_id']!==null||!hash_equals((string)$current['updated_at'],$expected)) throw new RuntimeException('De lead is intussen gewijzigd.');
   $changed=[];
   foreach ($fields as $key=>$_) if ((string)($current[$key]??'')!==$values[$key]) $changed[$key]=['old'=>$current[$key],'new'=>$values[$key]];
   if ($changed) {
    $sets=implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields)));
    $stmt=$db->prepare('UPDATE office_crm_leads SET '.$sets.',updated_at=NOW() WHERE id=?');
    $stmt->execute([...array_map(static fn($key)=>$values[$key]!==''?$values[$key]:null,array_keys($fields)),$id]);
    $note=json_encode(['reason'=>$reason,'changes'=>$changed],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $stmt=$db->prepare("INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,note) VALUES (?,?,'edited',?)");
    $stmt->execute([$id,(string)$operator->operatorId,$note]);
   }
   $db->commit();$_SESSION['crm_lead_edit_csrf']=bin2hex(random_bytes(32));
   header('Location: /office-test/crm/aanvragen/detail?id='.$id,true,303);exit;
  } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack();$error='Wijziging niet opgeslagen. Ververs de lead en probeer opnieuw.'; }
 }
 foreach ($fields as $key=>$_) if (isset($values[$key])) $lead[$key]=$values[$key];
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Lead bewerken</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a}main{max-width:760px;margin:32px auto;padding:24px;background:white;border-radius:12px}label{display:block;margin:14px 0}input,textarea{display:block;width:100%;box-sizing:border-box;padding:10px}button{background:#175a91;color:white;border:0;border-radius:6px;padding:12px 20px}</style></head><body><main><p><a href="/office-test/crm/aanvragen/detail?id='.$id.'">Terug naar lead</a></p><h1>Lead bewerken</h1>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_lead_edit_csrf']).'"><input type="hidden" name="expected_updated_at" value="'.$h($lead['updated_at']).'">';
foreach ($fields as $key=>$max) {
 echo '<label>'.$labels[$key];
 if ($key==='description') echo '<textarea name="'.$key.'" rows="5" maxlength="'.$max.'">'.$h($lead[$key]).'</textarea>';
 else echo '<input name="'.$key.'" value="'.$h($lead[$key]).'" maxlength="'.$max.'"'.($key==='contact_email'?' type="email"':'').($key==='title'?' required':'').'>';
 echo '</label>';
}
echo '<label>Reden van wijziging *<input name="reason" maxlength="500" required></label><button type="submit">Wijzigingen opslaan</button></form></main></body></html>';
