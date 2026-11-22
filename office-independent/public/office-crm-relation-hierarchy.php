<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
foreach (['OFFICE_TEST_CRM_ENABLED','OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED','OFFICE_CRM_RELATIONS_WRITE_ENABLED','OFFICE_TEST_CRM_HIERARCHY_SCHEMA_VERIFIED'] as $flag) if (getenv($flag)!=='enabled') { http_response_code(503); exit('Bedrijfsstructuur nog niet vrijgegeven.'); }
if (getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'||getenv('OFFICE_LOGIN_ENABLED')!=='enabled') { http_response_code(403); exit; }
$host=$_SERVER['HTTP_HOST']??'';$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!hash_equals($allowed,(string)$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
 foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
 $stmt=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$operator->operatorId]);if (!$stmt->fetchColumn()) { http_response_code(403); exit; }
 $childId=filter_var($_GET['relation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if (!is_int($childId)) { http_response_code(400); exit('Ongeldige relatie.'); }
 $stmt=$db->prepare("SELECT display_name FROM office_crm_relations WHERE id=? AND status='active'");$stmt->execute([$childId]);$name=$stmt->fetchColumn();
 if ($name===false) { http_response_code(404); exit('Relatie niet gevonden.'); }
 $stmt=$db->prepare('SELECT parent_relation_id FROM office_crm_relation_hierarchy WHERE child_relation_id=?');$stmt->execute([$childId]);$currentParent=$stmt->fetchColumn();
 $stmt=$db->prepare("SELECT id,display_name FROM office_crm_relations WHERE status='active' AND id<>? ORDER BY display_name LIMIT 500");$stmt->execute([$childId]);$parents=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('BREBO_CRM_HIERARCHY');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 if (!session_start()) { http_response_code(503); exit; }
}
if (!isset($_SESSION['crm_hierarchy_csrf'])) $_SESSION['crm_hierarchy_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
 if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['crm_hierarchy_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
 $raw=$_POST['parent_id']??'';$parent=$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $reason=is_string($_POST['reason']??null)?trim($_POST['reason']):'';
 if (($raw!==''&&!is_int($parent))||$parent===$childId||$reason===''||mb_strlen($reason)>500) $error='Controleer moederbedrijf en reden.';
 if (!$error) {
  try {
   $db->beginTransaction();
   // Serialize hierarchy writes on the same relation table to prevent concurrent cycles.
   $db->query('SELECT id FROM office_crm_relations ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
   $stmt=$db->prepare("SELECT id FROM office_crm_relations WHERE id=? AND status='active'");$stmt->execute([$childId]);if (!$stmt->fetchColumn()) throw new DomainException('Relatie is niet actief.');
   if ($parent!==null) {
    $stmt=$db->prepare("SELECT id FROM office_crm_relations WHERE id=? AND status='active'");$stmt->execute([$parent]);if (!$stmt->fetchColumn()) throw new DomainException('Moederbedrijf is niet actief.');
    $ancestor=$parent;$visited=[];
    while ($ancestor!==null) {
     if ($ancestor===$childId||isset($visited[$ancestor])) throw new DomainException('Cirkel in de bedrijfsstructuur is niet toegestaan.');
     $visited[$ancestor]=true;
     $stmt=$db->prepare('SELECT parent_relation_id FROM office_crm_relation_hierarchy WHERE child_relation_id=?');$stmt->execute([$ancestor]);
     $next=$stmt->fetchColumn();$ancestor=$next===false?null:(int)$next;
    }
   }
   $stmt=$db->prepare('SELECT parent_relation_id FROM office_crm_relation_hierarchy WHERE child_relation_id=? FOR UPDATE');$stmt->execute([$childId]);$old=$stmt->fetchColumn();$old=$old===false?null:(int)$old;
   if ($old!==$parent) {
    if ($parent===null) { $stmt=$db->prepare('DELETE FROM office_crm_relation_hierarchy WHERE child_relation_id=?');$stmt->execute([$childId]); }
    else { $stmt=$db->prepare('INSERT INTO office_crm_relation_hierarchy (child_relation_id,parent_relation_id) VALUES (?,?) ON DUPLICATE KEY UPDATE parent_relation_id=VALUES(parent_relation_id)');$stmt->execute([$childId,$parent]); }
    $stmt=$db->prepare('INSERT INTO office_crm_relation_hierarchy_events (child_relation_id,old_parent_relation_id,new_parent_relation_id,actor_operator_id,reason) VALUES (?,?,?,?,?)');
    $stmt->execute([$childId,$old,$parent,(string)$operator->operatorId,$reason]);
   }
   $db->commit();$_SESSION['crm_hierarchy_csrf']=bin2hex(random_bytes(32));
   header('Location: /office-test/crm/relaties/detail?id='.$childId,true,303);exit;
  } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Bedrijfsstructuur niet opgeslagen.'; }
 }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Bedrijfsstructuur</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a}main{max-width:680px;margin:36px auto;background:white;padding:26px;border-radius:12px}label{display:block;margin:20px 0}select,input{padding:10px;width:100%;box-sizing:border-box}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}</style></head><body><main><a href="/office-test/crm/relaties/detail?id='.$childId.'">Terug naar relatie</a><h1>Moederbedrijf instellen</h1><p>'.$h($name).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_hierarchy_csrf']).'"><label>Moederbedrijf<select name="parent_id"><option value="">Geen moederbedrijf</option>';
foreach ($parents as $row) echo '<option value="'.(int)$row['id'].'"'.((int)$currentParent===(int)$row['id']?' selected':'').'>'.$h($row['display_name']).'</option>';
echo '</select></label><label>Reden *<input name="reason" maxlength="500" required></label><button type="submit">Bedrijfsstructuur opslaan</button></form><p>Een bedrijf kan één direct moederbedrijf hebben en meerdere dochterbedrijven. Cirkelverwijzingen worden geweigerd.</p></main></body></html>';
