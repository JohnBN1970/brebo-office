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
$stmt=$db->prepare("SELECT id,title,stage,status FROM office_crm_opportunities WHERE id=? LIMIT 1");
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
$next=\Brebo\Office\Crm\OfficeCrmFunnel::NEXT[$item['stage']]??[];
$directEnabled=\Brebo\Office\Intake\OfficeMount::isTest()
    && getenv('OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_ENABLED')==='enabled'
    && getenv('OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_SCHEMA_VERIFIED')==='enabled';
if ($method==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals((string)$_SESSION['crm_opportunity_csrf'],$_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    $action=is_string($_POST['action']??null)?$_POST['action']:'';
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
        header('Location: /office-test/crm/dealen?updated='.$id,true,303); exit;
    } catch (Throwable $e) { $error='Fasewijziging mislukt.'; }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deal wijzigen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:750px;margin:32px auto;padding:20px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:18px 0}select{padding:10px;width:100%}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/dealen">Terug naar dealen</a></p><h1>'.$h($item['title']).'</h1><section><p>Huidige fase: '.$h(\Brebo\Office\Crm\OfficeCrmFunnel::label((string)$item['stage'])).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';

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
            if ($directEnabled) {
                // Do not offer creation when any direct/Calc mapping already exists,
                // or if the schema or explicit actor permission is unavailable.
                $check=$db->prepare(
                    'SELECT (SELECT COUNT(*) FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=?) AS calc_count,
                            (SELECT COUNT(*) FROM office_crm_direct_project_transfers WHERE opportunity_id=?) AS direct_count'
                );
                $check->execute([$id,$id]);
                $counts=$check->fetch(PDO::FETCH_ASSOC);
                $permission=$db->prepare(
                    "SELECT 1 FROM office_module_permissions WHERE operator_id=? AND module='projects' AND permission='create' LIMIT 1"
                );
                $permission->execute([$operator->operatorId]);
                if ((int)($counts['calc_count']??-1)===0 && (int)($counts['direct_count']??-1)===0
                    && $permission->fetchColumn()!==false) {
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
