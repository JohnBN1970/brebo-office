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
    $buildings=[];
    $permission=$db->prepare('SELECT can_edit_crm FROM office_operator_accounts WHERE id=? AND active=1');
    $permission->execute([$operator->operatorId]);
    if ((int)$permission->fetchColumn()!==1) { http_response_code(403); exit('Geen CRM-schrijfrecht.'); }
    $canCreateBuilding= getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BUILDING_CREATE_ENABLED')==='enabled' && getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BASE_PATH')==='/office-test';
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateService.php';
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
    if (getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')==='enabled') {
        if (getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BASE_PATH')==='/office-test') {
            $q=$db->prepare("SELECT b.id,b.building_name FROM office_buildings b WHERE b.active=1 AND (EXISTS (SELECT 1 FROM office_building_register_grants g WHERE g.building_id=b.id AND g.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_buildings pb JOIN office_project_memberships m ON m.project_id=pb.project_id WHERE pb.building_id=b.id AND m.operator_id=? AND m.permission IN ('read','manage'))) ORDER BY b.building_name,b.id");
            $q->execute([$operator->operatorId,$operator->operatorId]);
            $buildings=$q->fetchAll(PDO::FETCH_ASSOC);
        }
    }
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
$newBuilding=[]; $title=''; $error=''; $buildingIds=[]; $multiBuilding=getenv('OFFICE_CRM_DEAL_MULTI_BUILDINGS_SCHEMA_VERIFIED')==='enabled';
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
    $rawBuildings=$multiBuilding ? ($_POST['building_ids']??[]) : [$_POST['building_id']??'0'];
    $validBuilding=is_array($rawBuildings);
    $allowedBuildings=array_map(static fn($b)=>(int)$b['id'],$buildings);
    foreach (is_array($rawBuildings)?$rawBuildings:[] as $raw) {
        $value=is_scalar($raw)?filter_var($raw,FILTER_VALIDATE_INT):false;
        if (!$multiBuilding && $value===0) continue;
        if ($value===false || !in_array($value,$allowedBuildings,true)) {$validBuilding=false; continue;}
        $buildingIds[]=$value;
    }
    $buildingIds=array_values(array_unique($buildingIds));
    $validRelation=false;
    foreach ($relations as $relation) if ((int)$relation['id']===$relationId) $validRelation=true;
    if (in_array(($_POST['building_action']??null),['create','search_pdok'],true)) {
        if (!$canCreateBuilding) { http_response_code(403); exit('Geen recht om gebouwen aan te maken.'); }
        $newBuilding=is_array($_POST['new_building']??null)?$_POST['new_building']:[];
    unset($newBuilding['pdok_results']);
    if (($_POST['building_action']??null)==='search_pdok') {
        if (getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED')!=='enabled') {http_response_code(403);exit;}
        try {
            require_once dirname(__DIR__).'/src/Buildings/OfficePdokAddressLookup.php';
            require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
            $query=\Brebo\Office\Buildings\OfficeBuildingCreateForm::searchQuery($newBuilding);
            if (!is_string($query)) throw new DomainException('Invalid query');
            $newBuilding['pdok_results']=(new \Brebo\Office\Buildings\OfficePdokAddressLookup())->search($query);
            if ($newBuilding['pdok_results']===[]) $error='Geen BAG-adressen gevonden.';
        } catch (Throwable $e) {$error='PDOK zoeken mislukt. Controleer het adres of probeer opnieuw.';}
    } else
        if (!$validBuilding) { $error='Kies geldige gebouwen.'; }
        else try {
            $created=(new \Brebo\Office\Buildings\OfficeBuildingCreateService($db))->createForOperator($newBuilding,(int)$operator->operatorId,static fn():bool=>$canCreateBuilding);
            $buildings[]=['id'=>$created,'building_name'=>trim($newBuilding['building_name'])];
            $buildingIds=$multiBuilding?array_values(array_unique(array_merge($buildingIds,[(int)$created]))):[(int)$created];
            $newBuilding=[];
            $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
        } catch (DomainException $e) {
            $error=$e->getMessage()==='building_address_candidate_exists'?'Dit adres bestaat mogelijk al. Selecteer het bestaande gebouw.':'Ongeldige gebouwgegevens.';
        } catch (Throwable $e) { $error='Gebouw aanmaken mislukt.'; }
    }
    elseif (!$validBuilding || ($buildingIds!==[] && getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')!=='enabled')) $error='Kies een geldig gebouw.';
    elseif (!$validRelation || $title==='' || mb_strlen($title,'UTF-8')>255) $error='Kies een actieve relatie en vul een geldige titel in.';
    else {
        try {
            $db->beginTransaction();
            if ($leadId>0) {
                $lock=$db->prepare("SELECT id,relation_id,stage,status,converted_opportunity_id FROM office_crm_leads WHERE id=? FOR UPDATE");
                $lock->execute([$leadId]); $lead=$lock->fetch(PDO::FETCH_ASSOC);
                if (!$lead || $lead['stage']!=='qualified' || $lead['status']!=='active'
                    || $lead['converted_opportunity_id']!==null || (int)$lead['relation_id']!==$relationId) {
                    throw new DomainException('Lead is gewijzigd of heeft geen gekoppelde relatie.');
                }
            }
            if ($leadId>0 && getenv('OFFICE_CRM_LEAD_BUILDINGS_SCHEMA_VERIFIED')==='enabled') {
                $links=$db->prepare('SELECT building_id FROM office_crm_lead_buildings WHERE lead_id=? ORDER BY building_id FOR UPDATE');
                $links->execute([$leadId]);
                $buildingIds=array_values(array_unique(array_merge($buildingIds,array_map('intval',$links->fetchAll(PDO::FETCH_COLUMN)))));
                if (count($buildingIds)>1 && !$multiBuilding) throw new DomainException('Meerdere gebouwen nog niet beschikbaar.');
                if ($buildingIds!==[] && getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')!=='enabled') throw new DomainException('Gebouwkoppeling nog niet beschikbaar.');
            }
            $writePermission=$db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
            $writePermission->execute([$operator->operatorId]);
            if (!$writePermission->fetchColumn()) throw new DomainException('Geen CRM-schrijfrecht.');
            if ($buildingIds!==[]) {
                if (getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')!=='enabled' || getenv('OFFICE_BASE_PATH')!=='/office-test') throw new DomainException('Gebouwrechten niet beschikbaar.');
                $access=$db->prepare("SELECT b.id FROM office_buildings b WHERE b.id=? AND b.active=1 AND (EXISTS (SELECT 1 FROM office_building_register_grants g WHERE g.building_id=b.id AND g.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_buildings pb JOIN office_project_memberships m ON m.project_id=pb.project_id WHERE pb.building_id=b.id AND m.operator_id=? AND m.permission IN ('read','manage'))) FOR UPDATE");
                foreach ($buildingIds as $buildingId) {
                    $access->execute([$buildingId,$operator->operatorId,$operator->operatorId]);
                    if (!$access->fetchColumn()) throw new DomainException('Gebouw niet toegankelijk.');
                }
            }
            $id=(new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->create($relationId,$title);
            if ($buildingIds!==[]) {
                $link=$db->prepare('INSERT INTO office_crm_deal_buildings (opportunity_id,building_id) VALUES (?,?)');
                if (getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BASE_PATH')==='/office-test') {
                    if (getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')!=='enabled') throw new DomainException('building_audit_unavailable');
                    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingAudit.php';
                    $audit=new \Brebo\Office\Buildings\OfficeBuildingAudit($db);
                }
                foreach ($buildingIds as $buildingId) {
                    $link->execute([$id,$buildingId]);
                    if (isset($audit)) $audit->record((int)$buildingId,(int)$operator->operatorId,'linked','deal',(int)$id,['linked'=>false],['linked'=>true]);
                }
            }
            if ($leadId>0) {
                $update=$db->prepare("UPDATE office_crm_leads SET converted_opportunity_id=?,converted_at=NOW(),status='converted' WHERE id=? AND converted_opportunity_id IS NULL");
                $update->execute([$id,$leadId]);
                if ($update->rowCount()!==1) throw new DomainException('Lead is al omgezet.');
                $event=$db->prepare("INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,old_stage,new_stage,note) VALUES (?,?,'converted','qualified','converted',?)");
                $event->execute([$leadId,(string)$operator->operatorId,'Omgezet naar deal #'.$id]);
            }
            $db->commit();
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
echo '</select></label>';
if ($buildings!==[] || $canCreateBuilding) {
    echo $multiBuilding ? '<label>Gebouwen<select name="building_ids[]" multiple size="8">' : '<label>Gebouw<select name="building_id"><option value="0">Nog geen gebouw gekoppeld</option>';
    foreach ($buildings as $building) echo '<option value="'.(int)$building['id'].'"'.(in_array((int)$building['id'],$buildingIds,true)?' selected':'').'>'.$h($building['building_name']).'</option>';
    echo '</select></label>';
}
if ($canCreateBuilding) echo \Brebo\Office\Buildings\OfficeBuildingCreateForm::render($newBuilding);
echo '<label>Naam deal<input name="title" maxlength="255" required value="'.$h($title).'"></label><button type="submit">Deal aanmaken</button></form></section></main></body></html>';
