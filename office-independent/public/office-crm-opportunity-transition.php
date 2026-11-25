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
    if (getenv('OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Deals wijzigen niet ingeschakeld.'); }
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
} catch (Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
if ($id<1) { http_response_code(400); exit('Ongeldige deal.'); }
$stmt=$db->prepare("SELECT id,title,stage,status,revision FROM office_crm_opportunities WHERE id=? LIMIT 1");
$stmt->execute([$id]); $item=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) { http_response_code(404); exit('Deal niet gevonden.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_OPPORTUNITY_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_opportunity_csrf'])) $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';
$buildingReady=\Brebo\Office\Intake\OfficeMount::isTest() && $operator->canEditCrm && $item['status']==='open' && !in_array($item['stage'],['won','lost'],true) && getenv('OFFICE_CRM_DEAL_MULTI_BUILDINGS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')==='enabled';
$choices=[];$linked=[];$missing=[];
if ($buildingReady) {
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmDealBuildingLink.php';
    $linkService=new \Brebo\Office\Crm\OfficeCrmDealBuildingLink($db);
    $linked=$linkService->buildingIds($id);
    $q=$db->prepare("SELECT b.id,b.building_name FROM office_buildings b WHERE b.active=1 AND (EXISTS (SELECT 1 FROM office_building_register_grants g WHERE g.building_id=b.id AND g.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_buildings pb JOIN office_project_memberships m ON m.project_id=pb.project_id WHERE pb.building_id=b.id AND m.operator_id=? AND m.permission IN ('read','manage'))) ORDER BY b.building_name,b.id");
    $q->execute([$operator->operatorId,$operator->operatorId]);$choices=$q->fetchAll(PDO::FETCH_ASSOC);
    $missing=array_diff($linked,array_map('intval',array_column($choices,'id')));
}
$next=$item['status']==='open' ? (\Brebo\Office\Crm\OfficeCrmFunnel::NEXT[$item['stage']]??[]) : [];
$directEnabled=\Brebo\Office\Intake\OfficeMount::isTest()
    && getenv('OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_ENABLED')==='enabled'
    && getenv('OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_SCHEMA_VERIFIED')==='enabled';
if ($method==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals((string)$_SESSION['crm_opportunity_csrf'],$_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    $action=is_string($_POST['action']??null)?$_POST['action']:'';
    if ($action==='create_building') {
        if (!$buildingReady || $missing!==[] || getenv('OFFICE_BUILDING_CREATE_ENABLED')!=='enabled') {http_response_code(403);exit;}
        $revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);
        if ($revision===false || $revision!==(int)$item['revision'] || !is_array($_POST['new_building']??null)) {http_response_code(409);exit('Ververs de deal.');}
        require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateService.php';
        $db->beginTransaction();
        try {
            $p=$db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
            $p->execute([$operator->operatorId]);
            if (!$p->fetchColumn()) throw new DomainException('Geen schrijfrecht.');
            $bid=(new \Brebo\Office\Buildings\OfficeBuildingCreateService($db))->create($_POST['new_building'],static fn():bool=>true);
            $grant=$db->prepare('INSERT INTO office_building_register_grants (building_id,operator_id) VALUES (?,?)');
            $grant->execute([$bid,$operator->operatorId]);
            $linkService->replaceBuildings($id,array_merge($linked,[(int)$bid]),$revision);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(409);exit('Gebouwregistratie en koppeling niet opgeslagen. Controleer het adres en ververs de deal.');
        }
        $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
        header('Location: /office-test/crm/verkoopkansen',true,303);exit;
    }
    if ($action==='replace_buildings') {
        if (!$buildingReady || $missing!==[]) {http_response_code(403);exit;}
        $revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);
        $raw=$_POST['building_ids']??[];
        if ($revision===false || $revision<0 || !is_array($raw) || count($raw)>200) {http_response_code(400);exit;}
        $allowedIds=array_map('intval',array_column($choices,'id'));$ids=[];
        foreach ($raw as $v) {
            $bid=is_scalar($v)?filter_var($v,FILTER_VALIDATE_INT):false;
            if (!$bid || !in_array($bid,$allowedIds,true)) {http_response_code(403);exit;}
            $ids[]=$bid;
        }
        try {
            $linkService->replaceBuildings($id,$ids,$revision);
            $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
            header('Location: /office-test/crm/verkoopkansen',true,303);exit;
        } catch (Throwable $e) {http_response_code(409);exit('Gebouwkoppelingen niet opgeslagen. Ververs de pagina.');}
    }
    if ($action==='create_direct_project') {
        if (!$directEnabled || $item['stage']!=='won' || !in_array($item['status'],['closed','won'],true)) {
            http_response_code(403); exit('Directe projectoverdracht niet beschikbaar.');
        }
        try {
            require_once dirname(__DIR__).'/src/Crm/OfficeCrmDirectProjectAcceptance.php';
            $projectId=(new \Brebo\Office\Crm\OfficeCrmDirectProjectAcceptance($db))
                ->transferForOperator((int)$operator->operatorId,$id);
            $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
            header('Location: '.\Brebo\Office\Intake\OfficeMount::route('projecten/detail',['id'=>$projectId]),true,303);
            exit;
        } catch (DomainException $e) {
            http_response_code(409); exit('Projectoverdracht geweigerd; controleer rechten en opdrachtstatus.');
        } catch (Throwable $e) {
            http_response_code(503); exit('Projectoverdracht nog niet beschikbaar; geen project bevestigd.');
        }
    }
    if ($action!=='') { http_response_code(400); exit('Ongeldige opdracht.'); }
    if ($item['status'] !== 'open') { http_response_code(409); exit('Afgesloten deal kan niet worden gewijzigd.'); }
    $to=is_string($_POST['stage']??null)?$_POST['stage']:'';
    if (!in_array($to,$next,true)) { http_response_code(400); exit('Ongeldige faseovergang.'); }
    try {
        $ok=(new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->transition($id,(string)$item['stage'],$to);
        if (!$ok) { http_response_code(409); exit('De deal is intussen gewijzigd. Vernieuw de pagina.'); }
        $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
        header('Location: /office-test/crm/verkoopkansen?updated='.$id,true,303); exit;
    } catch (Throwable $e) { $error='Fasewijziging mislukt.'; }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deal wijzigen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:750px;margin:32px auto;padding:20px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:18px 0}select{padding:10px;width:100%}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/verkoopkansen">Terug naar deals</a></p><h1>'.$h($item['title']).'</h1><section><p>Huidige fase: '.$h(\Brebo\Office\Crm\OfficeCrmFunnel::label((string)$item['stage'])).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
if ($buildingReady) {
    echo '<h2>Gebouwen</h2>';
    if ($missing!==[]) echo '<p>Niet alle gekoppelde gebouwen zijn toegankelijk. Wijzigen is geblokkeerd.</p>';
    else {
        echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><input type="hidden" name="revision" value="'.(int)$item['revision'].'"><label>Gebouwen<select name="building_ids[]" multiple size="6">';
        foreach ($choices as $b) echo '<option value="'.(int)$b['id'].'"'.(in_array((int)$b['id'],$linked,true)?' selected':'').'>'.$h($b['building_name']).'</option>';
        echo '</select></label><button name="action" value="replace_buildings">Gebouwkoppelingen opslaan</button></form>';
        if (getenv('OFFICE_BUILDING_CREATE_ENABLED')==='enabled') {
            require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
            echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><input type="hidden" name="revision" value="'.(int)$item['revision'].'"><input type="hidden" name="action" value="create_building">'.\Brebo\Office\Buildings\OfficeBuildingCreateForm::render().'</form>';
        }
    }
}
// Projectless Deal -> Calc link. The launch endpoint rechecks session, authorization,
// CSRF, revision and the verified test database before any draft is created.
if ($item['status']==='open'
    && in_array($item['stage'],['prospect','opportunity','proposal','negotiation'],true)
    && getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')==='enabled'
    && getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')==='enabled') {
    echo '<p><a href="/office-test/calculatie/start?opportunity_id='.$id.'">Calculatie starten of openen</a> · zonder projectaanmaak</p>';
}


// A won CRM stage alone is not proof of a created Office project.
// Show only a persisted transfer; never invent a project link.
if ($item['stage']==='won') {
    try {
        require_once dirname(__DIR__).'/src/Crm/OfficeCrmProjectHandoff.php';
        $linkedProject=(new \Brebo\Office\Crm\OfficeCrmProjectHandoff($db))->projectId($id);
        if ($linkedProject !== null) {
            echo '<p>Office-project gekoppeld: <a href="/office-test/projecten/detail?id='.$linkedProject.'">Open project #'.$linkedProject.'</a></p>';
        } else {
            echo '<p>Deze deal is gegund, maar er is nog geen bevestigde projectoverdracht. Projectaanmaak moet afzonderlijk worden afgerond.</p>';
            if (!$directEnabled) {
                echo '<p role="status">Projectaanmaak is voor deze testomgeving nog niet geactiveerd. Laat de Office-test release- en featureflags controleren.</p>';
            } elseif (!in_array($item['status'], ['won','closed'], true)) {
                echo '<p role="status">Deze Deal heeft nog geen bevestigde akkoordstatus. Controleer de Dealstatus.</p>';
            } else {
                // No silent permission failure: the actor must explicitly have
                // native projects/create, even when the VPS schema preflight passes.
                // Never create a project on GET or grant permissions automatically.
                $check=$db->prepare(
                    'SELECT (SELECT COUNT(*) FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=?) AS calc_count,
                            (SELECT COUNT(*) FROM office_crm_direct_project_transfers WHERE opportunity_id=?) AS direct_count'
                );
                $check->execute([$id,$id]);
                $counts=$check->fetch(PDO::FETCH_ASSOC);
                $permission=$db->prepare(
                    "SELECT 1 FROM office_operator_accounts a
                     JOIN office_module_permissions g ON g.operator_id=a.id
                     WHERE a.id=? AND a.active=1 AND g.module='projects'
                       AND g.permission='create' LIMIT 1"
                );
                $permission->execute([$operator->operatorId]);
                if ((int)($counts['calc_count']??-1)>0 || (int)($counts['direct_count']??-1)>0) {
                    echo '<p role="status">Er bestaat een overdrachtsregistratie die niet als geldig project kan worden geopend. Laat deze eerst controleren; er wordt geen tweede project aangemaakt.</p>';
                } elseif ($permission->fetchColumn()===false) {
                    echo '<p role="status">Projectaanmaak niet toegestaan voor het huidige Office-account. De beheerder moet de bevoegdheid Projecten: aanmaken controleren.</p>';
                } else {
                    echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><input type="hidden" name="action" value="create_direct_project"><button type="submit">Maak project voor directe opdracht</button></form>';
                }
            }
        }
    } catch (Throwable $e) {
        echo '<p>Projectoverdracht nog niet beschikbaar; de CRM-status is geen bevestiging van projectaanmaak.</p>';
    }
}

if ($next===[]) echo '<p>Geen volgende fase beschikbaar.</p>';
else {
    echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><label>Volgende fase<select name="stage">';
    foreach ($next as $stage) echo '<option value="'.$h($stage).'">'.$h(\Brebo\Office\Crm\OfficeCrmFunnel::label($stage)).'</option>';
    echo '</select></label><button type="submit">Fase bevestigen</button></form>';
}
echo '</section></main></body></html>';
