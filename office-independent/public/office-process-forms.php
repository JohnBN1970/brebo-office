<?php
declare(strict_types=1);
// Isolated, read-only Office-test entry point. No form writes or role preview.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_TEST_PROCESS_FORMS_CATALOG_ENABLED') !== 'enabled') {
    http_response_code(503); exit('Procesformulieren niet beschikbaar.');
}
$host=(string)($_SERVER['HTTP_HOST']??'');
$expected=(string)(getenv('OFFICE_PUBLIC_HOST')?:'');
if ($expected==='' || !hash_equals($expected,$host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn()===false) { http_response_code(403); exit('Geen toegang.'); }
    require_once dirname(__DIR__).'/src/Process/OfficeProcessFormCatalog.php';
    $catalog=\Brebo\Office\Process\OfficeProcessFormCatalog::all();
    // Project-scoped catalog: never expose project context without membership.
    $projectForms=[];
    $projectIdRaw=(string)($_GET['project_id']??'');
    if ($projectIdRaw!=='') {
        if (!preg_match('/^[1-9][0-9]{0,17}$/D',$projectIdRaw)) {
            http_response_code(400); exit('Ongeldig project.');
        }
        $membership=$db->prepare("SELECT 1 FROM office_project_memberships m
            INNER JOIN office_operator_accounts a ON a.id=m.operator_id
            INNER JOIN office_projects p ON p.id=m.project_id
            WHERE m.project_id=? AND m.operator_id=? AND m.permission IN ('read','manage') AND a.active=1 LIMIT 1");
        $membership->execute([(int)$projectIdRaw,$operator->operatorId]);
        if ($membership->fetchColumn()===false) { http_response_code(403); exit('Geen toegang tot dit project.'); }
        require_once dirname(__DIR__).'/src/Process/OfficeProcessFormContext.php';
        $catalog=array_filter($catalog,static fn(array $entry,string $code):bool=>
            \Brebo\Office\Process\OfficeProcessFormContext::accepts($code,'project'),ARRAY_FILTER_USE_BOTH);
        // No runtime migrations; never expose rows without project membership.
        if (getenv('OFFICE_TEST_PROCESS_FORMS_SCHEMA_VERIFIED')==='enabled') {
            require_once dirname(__DIR__).'/src/Process/OfficeProcessFormSchemaPreflight.php';
            $preflight=\Brebo\Office\Process\OfficeProcessFormSchemaPreflight::verify($db);
            if ($preflight['ready']!==true) { http_response_code(503); exit('Formulieropslag nog niet gereed.'); }
            $formsStmt=$db->prepare("SELECT id,document_code,status,revision,updated_at
                FROM office_process_form_instances
                WHERE context_type='project' AND context_id=?
                ORDER BY updated_at DESC,id DESC LIMIT 100");
            $formsStmt->execute([(int)$projectIdRaw]);
            $projectForms=array_values(array_filter($formsStmt->fetchAll(PDO::FETCH_ASSOC),
                static fn(array $row):bool=>isset($catalog[(string)$row['document_code']])));
        }
    }
} catch (\Throwable $e) { http_response_code(503); exit('Formuliercatalogus tijdelijk niet beschikbaar.'); }
$h=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Procesformulieren · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1050px;margin:32px auto;padding:20px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:22px}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #dbe1e8}a{color:#175a91}</style></head><body><main><p><a href="/office-test/">Office-overzicht</a></p><section><h1>Proceshandboek · formulieren</h1><p>Catalogus ter inzage. Invullen en goedkeuren zijn nog niet vrijgegeven; toegang tot individuele dossiers wordt afzonderlijk gecontroleerd.</p><table><thead><tr><th>Code</th><th>Formulier</th><th>Module</th></tr></thead><tbody>';
foreach ($catalog as $code=>$details) {
    echo '<tr><td>'.$h($code).'</td><td>'.$h($details[0]??'').'</td><td>'.$h($details[1]??'').'</td></tr>';
}
echo '</tbody></table>';
if ($projectIdRaw!=='' && getenv('OFFICE_TEST_PROCESS_FORMS_SCHEMA_VERIFIED')==='enabled') {
    echo '<h2>Opgeslagen projectformulieren</h2>';
    if ($projectForms===[]) echo '<p>Nog geen formulieren geregistreerd.</p>';
    else {
        echo '<table><thead><tr><th>Nummer</th><th>Code</th><th>Status</th><th>Revisie</th><th>Gewijzigd</th></tr></thead><tbody>';
        foreach ($projectForms as $form) {
            echo '<tr><td>'.$h($form['id']).'</td><td>'.$h($form['document_code']).'</td><td>'.$h($form['status']).'</td><td>'.$h($form['revision']).'</td><td>'.$h($form['updated_at']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
}
echo '</section></main></body></html>';
