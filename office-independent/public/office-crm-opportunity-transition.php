<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('Verkoopkansen niet beschikbaar.'); }
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
    if (getenv('OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Verkoopkansen wijzigen niet ingeschakeld.'); }
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
} catch (Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
if ($id<1) { http_response_code(400); exit('Ongeldige verkoopkans.'); }
$stmt=$db->prepare("SELECT id,title,stage FROM office_crm_opportunities WHERE id=? AND status='open' LIMIT 1");
$stmt->execute([$id]); $item=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) { http_response_code(404); exit('Verkoopkans niet gevonden of gesloten.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_OPPORTUNITY_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_opportunity_csrf'])) $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$error='';
$handoffNotice='';
try {
    $acceptanceQuery=$db->prepare('SELECT calculation_id, accepted_revision, status FROM office_crm_calculation_acceptances WHERE opportunity_id=? LIMIT 1');
    $acceptanceQuery->execute([$id]);
    $acceptance=$acceptanceQuery->fetch(PDO::FETCH_ASSOC);
    if (is_array($acceptance) && $acceptance['status']==='accepted') {
        $handoffNotice='Geaccepteerde calculatie #'.(int)$acceptance['calculation_id'].' (versie '.(string)$acceptance['accepted_revision'].'). Bij akkoord is eerst een vergrendelde werkbegroting en projectoverdracht vereist. Deze actie is nog niet beschikbaar via dit formulier.';
    } elseif (is_array($acceptance)) {
        $handoffNotice='Calculatie gekoppeld, maar nog niet geaccepteerd. De opdracht kan alleen via de geldende CRM-route worden verwerkt.';
    } else {
        $handoffNotice='Geen geaccepteerde calculatie gekoppeld. Een directe opdracht zonder calculatie is mogelijk.';
    }
} catch (Throwable $e) {
    $handoffNotice='Calculatiestatus niet beschikbaar. Akkoord wordt veilig geblokkeerd totdat de acceptatiecontrole werkt.';
}
$next=\Brebo\Office\Crm\OfficeCrmFunnel::NEXT[$item['stage']]??[];
if ($method==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals((string)$_SESSION['crm_opportunity_csrf'],$_POST['csrf'])) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
    $to=is_string($_POST['stage']??null)?$_POST['stage']:'';
    if (!in_array($to,$next,true)) { http_response_code(400); exit('Ongeldige faseovergang.'); }
    try {
        if ($to==='won') {
            require_once dirname(__DIR__).'/src/Crm/OfficeCrmWonTransition.php';
            (new \Brebo\Office\Crm\OfficeCrmWonTransition($db))->apply($id,(string)$item['stage']);
        } else {
            $ok=(new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->transition($id,(string)$item['stage'],$to);
            if (!$ok) { http_response_code(409); exit('De verkoopkans is intussen gewijzigd. Vernieuw de pagina.'); }
        }
        $_SESSION['crm_opportunity_csrf']=bin2hex(random_bytes(32));
        header('Location: /office-test/crm/verkoopkansen?updated='.$id,true,303); exit;
    } catch (Throwable $e) { $error='Fasewijziging mislukt.'; }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopkans wijzigen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:750px;margin:32px auto;padding:20px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:18px 0}select{padding:10px;width:100%}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/verkoopkansen">Terug naar verkoopkansen</a></p><h1>'.$h($item['title']).'</h1><section><p>Huidige fase: '.$h(\Brebo\Office\Crm\OfficeCrmFunnel::label((string)$item['stage'])).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<aside style="background:#eaf2fb;border-left:4px solid #175a91;padding:14px 18px;margin:18px 0;border-radius:6px"><strong>Overdracht naar uitvoering</strong><p>'.$h($handoffNotice).'</p></aside>';
if ($next===[]) echo '<p>Geen volgende fase beschikbaar.</p>';
else {
    echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_opportunity_csrf']).'"><label>Volgende fase<select name="stage">';
    foreach ($next as $stage) echo '<option value="'.$h($stage).'">'.$h(\Brebo\Office\Crm\OfficeCrmFunnel::label($stage)).'</option>';
    echo '</select></label><button type="submit">Fase bevestigen</button></form>';
}
echo '</section></main></body></html>';
