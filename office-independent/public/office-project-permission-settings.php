<?php
declare(strict_types=1);
// Office-test site-administrator-only rights management. Not a public role editor.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','POST'],true)) {http_response_code(405);exit;}
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
    || getenv('OFFICE_TEST_BLUEPRINT_ADMIN_ENABLED')!=='enabled') {http_response_code(404);exit;}
$secret=getenv('OFFICE_TEST_BLUEPRINT_ADMIN_CSRF_KEY');
if (!is_string($secret) || strlen($secret)<32) {http_response_code(503);exit('Beheerconfiguratie ontbreekt.');}
foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__).'/src/Intake/'.$file;
}
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
require_once dirname(__DIR__).'/src/Approval/OfficeTestProjectCreationPermissionAdmin.php';
$h=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try {
    $dsn=getenv('OFFICE_INTAKE_PDO_DSN');
    if (!is_string($dsn)||$dsn==='') throw new RuntimeException('Database unavailable');
    $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $sessions=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$sessions->currentOperator();
    if ($operator===null) {http_response_code(401);exit('Aanmelden vereist.');}
    if (!(new \Brebo\Office\Approval\OfficeTestSiteAdministratorAccess($db))
        ->isAuthorized((string)$operator->operatorId,true)) {http_response_code(403);exit('Alleen sitebeheerder.');}
    // Fail closed before rendering an actionable form if the audit table is uninstalled.
    $auditReady=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_test_project_create_permission_events'
        AND COLUMN_NAME IN ('actor_operator_id','target_operator_id','action','reason','created_at')")
        ->fetchColumn();
    if ((int)$auditReady!==5) {http_response_code(503);exit('Auditregistratie voor projectrechten nog niet geïnstalleerd.');}
    $csrf=hash_hmac('sha256','project-permissions:'.$operator->sessionId,$secret);
    $permissions=new \Brebo\Office\Approval\OfficeTestProjectCreationPermissionAdmin($db);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $submitted=$_POST['csrf']??null;
        if (!is_string($submitted)||!hash_equals($csrf,$submitted)) {http_response_code(403);exit('Formulierbeveiliging ongeldig.');}
        $target=$_POST['operator_id']??'';
        $action=$_POST['permission_action']??'';
        $reason=$_POST['reason']??'';
        if (!is_string($target)||!is_string($action)||!is_string($reason)) {http_response_code(400);exit('Ongeldige aanvraag.');}
        try {
            $permissions->change((string)$operator->operatorId,$target,$action,$reason);
        } catch (DomainException $e) {http_response_code(422);exit($h($e->getMessage()));}
        header('Location: /office-test/instellingen/organisatie/projectrechten?opgeslagen=1',true,303);
        exit;
    }
    $rows=$db->query("SELECT a.id,a.username, CASE WHEN p.operator_id IS NULL THEN 0 ELSE 1 END AS can_create
        FROM office_operator_accounts a LEFT JOIN office_module_permissions p
        ON p.operator_id=a.id AND p.module='projects' AND p.permission='create'
        WHERE a.active=1 ORDER BY a.id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Office-test project permission admin unavailable: '.get_class($e));
    http_response_code(503);exit('Projectrechten tijdelijk niet beschikbaar.');
}
$saved=($_GET['opgeslagen']??'')==='1'?'<p role="status">Projectrechten gecontroleerd en opgeslagen.</p>':'';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Organisatie · Projectrechten · BREBO Office</title><style>body{font:16px system-ui;background:#f5f7fb;color:#172838;max-width:900px;margin:3rem auto;padding:0 1rem}main{background:white;padding:2rem;border:1px solid #d7e0e9;border-radius:12px}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:.7rem;border-bottom:1px solid #ddd}select,input,button{font:inherit;padding:.6rem}label{display:block;margin:1rem 0}button{background:#175a91;color:white;border:0;border-radius:5px}</style></head><body><main><p><a href="/office-test/">Office</a> / Instellingen / Organisatie &amp; rollen</p><h1>Projectrechten</h1>'.$saved.'<p>Alleen actieve Office-accounts. Rechten worden uitsluitend expliciet door de sitebeheerder ingesteld en met reden vastgelegd. Rolpreview geeft geen extra bevoegdheden.</p>';
echo '<table><thead><tr><th>Office-operator</th><th>Project aanmaken</th></tr></thead><tbody>';
foreach ($rows as $row) echo '<tr><td>'.$h($row['username']).' (#'.$h($row['id']).')</td><td>'.((int)$row['can_create']===1?'Ja':'Nee').'</td></tr>';
echo '</tbody></table><h2>Bevoegdheid aanpassen</h2><form method="post" action="/office-test/instellingen/organisatie/projectrechten"><input type="hidden" name="csrf" value="'.$h($csrf).'"><label>Medewerker<select required name="operator_id"><option value="">Kies medewerker</option>';
foreach ($rows as $row) echo '<option value="'.$h($row['id']).'">'.$h($row['username']).' (#'.$h($row['id']).') ('.((int)$row['can_create']===1?'heeft recht':'geen recht').')</option>';
echo '</select></label><label>Actie<select name="permission_action" required><option value="grant">Projecten aanmaken toestaan</option><option value="revoke">Recht op projectaanmaak intrekken</option></select></label><label>Reden<input type="text" name="reason" required maxlength="255" autocomplete="off"></label><button type="submit">Bevestig wijziging</button></form></main></body></html>';
