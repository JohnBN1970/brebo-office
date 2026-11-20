<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('Deals niet beschikbaar.'); }
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed==='' || !hash_equals($allowed,(string)$host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
    if (getenv('OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Deals invoeren niet ingeschakeld.'); }
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
    $relations=$db->query("SELECT id,display_name FROM office_crm_relations WHERE status='active' ORDER BY display_name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_OPPORTUNITY_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_opportunity_csrf'])) $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$relationId=filter_var($_GET['relation_id']??0,FILTER_VALIDATE_INT)?:0;
$title=''; $error='';
$leadId=filter_var($_GET['lead_id']??0,FILTER_VALIDATE_INT)?:0;
if ($method==='GET' && $leadId>0) {
    $leadStmt=$db->prepare("SELECT title,relation_id,stage,status,converted_opportunity_id FROM office_crm_leads WHERE id=?");
    $leadStmt->execute([$leadId]); $lead=$leadStmt->fetch(PDO::FETCH_ASSOC);
    if ($lead && $lead['stage']==='qualified' && $lead['status']==='active' && !$lead['converted_opportunity_id']) {
        $title=(string)$lead['title']; $relationId=(int)$lead['relation_id'];
    } else { $leadId=0; }
}
if ($method==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals((string)$_SESSION['crm_opportunity_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
    $relationId=filter_var($_POST['relation_id']??0,FILTER_VALIDATE_INT)?:0;
    $leadId=filter_var($_POST['lead_id']??0,FILTER_VALIDATE_INT)?:0;
    $title=is_string($_POST['title']??null)?trim($_POST['title']):'';
    $validRelation=false;
    foreach ($relations as $relation) if ((int)$relation['id']===$relationId) $validRelation=true;
    if (!$validRelation || $title==='' || mb_strlen($title,'UTF-8')>255) $error='Kies een actieve relatie en vul een geldige titel in.';
    else {
        try {
            if ($leadId>0) {
                $db->beginTransaction();
                $lock=$db->prepare("SELECT id,relation_id,stage,status,converted_opportunity_id FROM office_crm_leads WHERE id=? FOR UPDATE");
                $lock->execute([$leadId]); $lead=$lock->fetch(PDO::FETCH_ASSOC);
                if (!$lead || $lead['stage']!=='qualified' || $lead['status']!=='active'
                    || $lead['converted_opportunity_id']!==null || (int)$lead['relation_id']!==$relationId) {
                    throw new DomainException('Lead is gewijzigd of heeft geen gekoppelde relatie.');
                }
            }
            $id=(new \\Brebo\\Office\\Crm\\OfficeCrmOpportunityRepository($db))->create($relationId,$title);
            if ($leadId>0) {
                $update=$db->prepare("UPDATE office_crm_leads SET converted_opportunity_id=?,converted_at=NOW(),status='converted' WHERE id=? AND converted_opportunity_id IS NULL");
                $update->execute([$id,$leadId]);
                if ($update->rowCount()!==1) throw new DomainException('Lead is al omgezet.');
                $event=$db->prepare("INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,old_stage,new_stage,note) VALUES (?,?,'converted','qualified','converted',?)");
                $event->execute([$leadId,(string)$operator->operatorId,'Omgezet naar deal #'.$id]);
                $db->commit();
            }
            $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
            header('Location: /office-test/crm/verkoopkansen?created='.$id,true,303); exit;
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); $error='Deal opslaan mislukt. Controleer of de lead nog gekwalificeerd en niet omgezet is.'; }
    }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nieuwe deal · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:750px;margin:32px auto;padding:20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:18px 0}select,input{padding:10px;width:100%;box-sizing:border-box}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/verkoopkansen">Terug naar deals</a></p><h1>Nieuwe deal</h1><section>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><input type="hidden" name="lead_id" value="'.(int)$leadId.'"><label>Relatie<select name="relation_id" required><option value="">Kies een relatie</option>';
foreach ($relations as $relation) echo '<option value="'.(int)$relation['id'].'"'.((int)$relation['id']===$relationId?' selected':'').'>'.$h($relation['display_name']).'</option>';
echo '</select></label><label>Naam deal<input name="title" maxlength="255" required value="'.$h($title).'"></label><button type="submit">Deal aanmaken</button></form></section></main></body></html>';
