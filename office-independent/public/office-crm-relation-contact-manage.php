<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'||getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'||getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')!=='enabled'||getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')!=='enabled') { http_response_code(503); exit('Contactbeheer niet beschikbaar.'); }
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
 $relationId=filter_var($_GET['relation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $contactId=filter_var($_GET['contact_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if (!is_int($relationId)||!is_int($contactId)) { http_response_code(400); exit('Ongeldige koppeling.'); }
 $stmt=$db->prepare("SELECT p.given_name,p.family_name,c.job_title,c.is_primary FROM office_crm_relation_contacts c JOIN office_crm_contact_people p ON p.id=c.contact_id JOIN office_crm_relations r ON r.id=c.relation_id WHERE c.relation_id=? AND c.contact_id=? AND r.status='active'");
 $stmt->execute([$relationId,$contactId]);$contact=$stmt->fetch(PDO::FETCH_ASSOC);
 if (!$contact) { http_response_code(404); exit('Koppeling niet gevonden.'); }
} catch(Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('BREBO_CRM_CONTACT_MANAGE');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_contact_manage_csrf'])) $_SESSION['crm_contact_manage_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
 if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['crm_contact_manage_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
 $action=$_POST['action']??'';
 $reason=is_string($_POST['reason']??null)?trim($_POST['reason']):'';
 $job=is_string($_POST['job_title']??null)?trim($_POST['job_title']):'';
 if (!in_array($action,['save','unlink'],true)||$reason===''||mb_strlen($reason)>500||mb_strlen($job)>128) $error='Controleer de actie, functie en verplichte reden.';
 if (!$error && $action==='unlink' && ($_POST['confirm']??'')!=='ONTKOPPELEN') $error='Typ ONTKOPPELEN om te bevestigen.';
 if (!$error) {
  try {
   $db->beginTransaction();
   $stmt=$db->prepare('SELECT job_title,is_primary FROM office_crm_relation_contacts WHERE relation_id=? AND contact_id=? FOR UPDATE');$stmt->execute([$relationId,$contactId]);$previous=$stmt->fetch(PDO::FETCH_ASSOC);
   if (!$previous) throw new RuntimeException('Koppeling is al gewijzigd.');
   if ($action==='unlink') {
    $stmt=$db->prepare('DELETE FROM office_crm_relation_contacts WHERE relation_id=? AND contact_id=?');$stmt->execute([$relationId,$contactId]);
   } else {
    $stmt=$db->prepare('UPDATE office_crm_relation_contacts SET job_title=?,is_primary=? WHERE relation_id=? AND contact_id=?');
    $stmt->execute([$job?:null,($_POST['is_primary']??'')==='1'?1:0,$relationId,$contactId]);
   }
   // Audit storage must be installed and verified before enabling this write endpoint.
   $stmt=$db->prepare('INSERT INTO office_crm_relation_contact_events (relation_id,contact_id,actor_operator_id,event_type,note) VALUES (?,?,?,?,?)');
   $stmt->execute([$relationId,$contactId,(string)$operator->operatorId,$action,json_encode(['reason'=>$reason,'before'=>$previous,'after'=>$action==='save'?['job_title'=>$job,'is_primary'=>($_POST['is_primary']??'')==='1']:null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   $db->commit();$_SESSION['crm_contact_manage_csrf']=bin2hex(random_bytes(32));
   header('Location: /office-test/crm/relaties/detail?id='.$relationId,true,303);exit;
  } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack();$error='Wijziging niet opgeslagen; controleer de auditinstallatie.'; }
 }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Contactkoppeling beheren</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a}main{max-width:650px;margin:36px auto;background:white;padding:26px;border-radius:12px}label{display:block;margin:15px 0}input{padding:9px}button{padding:12px 18px;background:#175a91;color:white;border:0;border-radius:6px}</style></head><body><main><a href="/office-test/crm/relaties/detail?id='.$relationId.'">Terug naar relatie</a><h1>Contactkoppeling beheren</h1><p>'.$h(trim($contact['given_name'].' '.$contact['family_name'])).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_contact_manage_csrf']).'"><input type="hidden" name="action" value="save"><label>Functie bij dit bedrijf <input name="job_title" maxlength="128" value="'.$h($contact['job_title']).'"></label><label><input type="checkbox" name="is_primary" value="1"'.($contact['is_primary']?' checked':'').'> Primair contact</label><label>Reden * <input name="reason" maxlength="500" required></label><button type="submit">Koppeling bijwerken</button></form>';
echo '<h2>Ontkoppelen</h2><p>De contactpersoon blijft bestaan bij andere bedrijven.</p><form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_contact_manage_csrf']).'"><input type="hidden" name="action" value="unlink"><label>Reden * <input name="reason" maxlength="500" required></label><label>Typ ONTKOPPELEN <input name="confirm" required></label><button type="submit">Ontkoppelen bevestigen</button></form></main></body></html>';
