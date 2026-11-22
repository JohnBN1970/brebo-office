<?php
declare(strict_types=1);
// Isolated Office-test form entry point. Mutations require explicit write flag, real project rights and CSRF.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''),['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && getenv('OFFICE_TEST_PROCESS_FORMS_WRITE_ENABLED') !== 'enabled') { http_response_code(403); exit('Formuliermutaties uitgeschakeld.'); }
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
    $csrfCookie=$_COOKIE['office_process_forms_csrf']??'';
    if (!is_string($csrfCookie) || !preg_match('/^[a-f0-9]{64}$/D',$csrfCookie)) {
        $csrfCookie=bin2hex(random_bytes(32));
        setcookie('office_process_forms_csrf',$csrfCookie,['expires'=>0,'path'=>'/office-test','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    }
    $projectForms=[];
    $selectedForm=null;
    $formEvents=[];
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
        if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
            if (getenv('OFFICE_TEST_PROCESS_FORMS_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('Formulieropslag niet geverifieerd.'); }
            require_once dirname(__DIR__).'/src/Process/OfficeProcessFormSchemaPreflight.php';
            if (\Brebo\Office\Process\OfficeProcessFormSchemaPreflight::verify($db)['ready']!==true) { http_response_code(503); exit('Formulieropslag niet gereed.'); }
            $submittedCsrf=$_POST['csrf_token']??null;
            if (!is_string($submittedCsrf) || !hash_equals($csrfCookie,$submittedCsrf)) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
            $action=$_POST['action']??null;
            if (!in_array($action,['create_concept','revise_concept','submit_concept','start_review','return','reject'],true)) { http_response_code(400); exit('Onbekende formulieractie.'); }
            $manage=$db->prepare("SELECT 1 FROM office_project_memberships WHERE project_id=? AND operator_id=? AND permission='manage' LIMIT 1");
            $manage->execute([(int)$projectIdRaw,$operator->operatorId]);
            if ($manage->fetchColumn()===false) { http_response_code(403); exit('Geen schrijfrechten voor dit project.'); }
            require_once dirname(__DIR__).'/src/Process/OfficeProcessFormRepository.php';
            $repository=new \Brebo\Office\Process\OfficeProcessFormRepository($db);
            if ($action==='create_concept') {
                $code=$_POST['document_code']??null;
                if (!is_string($code) || !isset($catalog[$code])) { http_response_code(422); exit('Formuliercode niet toegestaan.'); }
                $id=$repository->create($code,'1.0','project',(int)$projectIdRaw,(string)$operator->operatorId,[],'Concept aangemaakt');
            } elseif ($action!=='revise_concept') {
                $id=filter_var($_POST['form_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $revision=filter_var($_POST['expected_revision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $reason=$_POST['change_reason']??null;
                if (!is_int($id) || !is_int($revision) || !is_string($reason)) { http_response_code(422); exit('Ongeldige beslissing.'); }
                $form=$repository->load($id);
                if ($form===null || $form['context_type']!=='project' || (int)$form['context_id']!==(int)$projectIdRaw
                    || !isset($catalog[(string)$form['document_code']])) { http_response_code(404); exit('Formulier niet gevonden.'); }
                $workflowAction=$action==='submit_concept'?'submit':$action;
                // Four-eyes remains the safe default; the approval action is
            // intentionally unavailable until independent reviewer mandate
            // and blueprint policy are wired to the real Office roles.
            $repository->transition($id,$revision,$workflowAction,(string)$operator->operatorId,true,true,$reason);
            } else {
                $id=filter_var($_POST['form_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $revision=filter_var($_POST['expected_revision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $reason=$_POST['change_reason']??null;
                $fields=$_POST['fields']??null;
                if (!is_int($id) || !is_int($revision) || !is_string($reason) || !is_array($fields)) {
                    http_response_code(422); exit('Ongeldige formulierwijziging.');
                }
                $form=$repository->load($id);
                if ($form===null || $form['context_type']!=='project' || (int)$form['context_id']!==(int)$projectIdRaw
                    || !isset($catalog[(string)$form['document_code']])) { http_response_code(404); exit('Formulier niet gevonden.'); }
                $repository->revise($id,$revision,(string)$operator->operatorId,$fields,$reason);
            }
            header('Location: /office-test/procesformulieren?project_id='.rawurlencode($projectIdRaw).'&form_id='.$id,true,303);
            exit;
        }
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
            // The detail query is bound to the verified project; a foreign
            // form ID cannot be used to cross into another project dossier.
            $formIdRaw=(string)($_GET['form_id']??'');
            if ($formIdRaw!=='') {
                if (!preg_match('/^[1-9][0-9]{0,17}$/D',$formIdRaw)) {
                    http_response_code(400); exit('Ongeldig formuliernummer.');
                }
                $detail=$db->prepare("SELECT id,document_code,status,revision,document_version,data_json
                    FROM office_process_form_instances
                    WHERE id=? AND context_type='project' AND context_id=? LIMIT 1");
                $detail->execute([(int)$formIdRaw,(int)$projectIdRaw]);
                $selectedForm=$detail->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($selectedForm===null || !isset($catalog[(string)$selectedForm['document_code']])) {
                    http_response_code(404); exit('Formulier niet gevonden.');
                }
                $decoded=json_decode((string)$selectedForm['data_json'],true,512,JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) throw new \RuntimeException('Invalid form payload');
                $selectedForm['data']=$decoded;
                unset($selectedForm['data_json']);
                // Audit is read-only and strictly scoped to this form, which
                // was already verified against the authenticated project.
                $history=$db->prepare("SELECT revision,actor_operator_id,event_type,reason,created_at
                    FROM office_process_form_events WHERE form_id=?
                    ORDER BY revision DESC LIMIT 100");
                $history->execute([(int)$selectedForm['id']]);
                $formEvents=$history->fetchAll(PDO::FETCH_ASSOC);
            }
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
if ($projectIdRaw!=='' && getenv('OFFICE_TEST_PROCESS_FORMS_WRITE_ENABLED')==='enabled'
    && getenv('OFFICE_TEST_PROCESS_FORMS_SCHEMA_VERIFIED')==='enabled') {
    echo '<h2>Nieuw concept</h2><form method="post" action="/office-test/procesformulieren?project_id='.$h($projectIdRaw).'">';
    echo '<input type="hidden" name="csrf_token" value="'.$h($csrfCookie).'"><input type="hidden" name="action" value="create_concept">';
    echo '<label>Formulier <select name="document_code">';
    foreach ($catalog as $code=>$details) echo '<option value="'.$h($code).'">'.$h($code).' — '.$h($details[0]).'</option>';
    echo '</select></label> <button type="submit">Concept aanmaken</button></form>';
}
if ($projectIdRaw!=='' && getenv('OFFICE_TEST_PROCESS_FORMS_SCHEMA_VERIFIED')==='enabled') {
    echo '<h2>Opgeslagen projectformulieren</h2>';
    if ($projectForms===[]) echo '<p>Nog geen formulieren geregistreerd.</p>';
    else {
        echo '<table><thead><tr><th>Nummer</th><th>Code</th><th>Status</th><th>Revisie</th><th>Gewijzigd</th></tr></thead><tbody>';
        foreach ($projectForms as $form) {
            echo '<tr><td><a href="/office-test/procesformulieren?project_id='.$h($projectIdRaw).'&amp;form_id='.$h($form['id']).'">'.$h($form['id']).'</a></td><td>'.$h($form['document_code']).'</td><td>'.$h($form['status']).'</td><td>'.$h($form['revision']).'</td><td>'.$h($form['updated_at']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
}
if ($selectedForm!==null) {
    echo '<h2>Formulier '.$h($selectedForm['document_code']).'</h2><p>Status: '.$h($selectedForm['status']).' · Revisie: '.$h($selectedForm['revision']).' · Versie: '.$h($selectedForm['document_version']).'</p>';
    echo '<dl>';
    foreach ($selectedForm['data'] as $key=>$value) {
        if (!is_string($key) || !(is_scalar($value) || $value===null)) continue;
        echo '<dt><strong>'.$h($key).'</strong></dt><dd>'.nl2br($h($value),false).'</dd>';
    }
    if ($selectedForm['status']==='concept' && getenv('OFFICE_TEST_PROCESS_FORMS_WRITE_ENABLED')==='enabled') {
        echo '<h3>Concept bewerken</h3><form method="post" action="/office-test/procesformulieren?project_id='.$h($projectIdRaw).'">';
        echo '<input type="hidden" name="csrf_token" value="'.$h($csrfCookie).'"><input type="hidden" name="action" value="revise_concept">';
        echo '<input type="hidden" name="form_id" value="'.$h($selectedForm['id']).'"><input type="hidden" name="expected_revision" value="'.$h($selectedForm['revision']).'">';
        foreach ($selectedForm['data'] as $key=>$value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,79}$/D',$key) || !(is_scalar($value) || $value===null)) continue;
            echo '<p><label>'.$h($key).' <textarea name="fields['.$h($key).']">'.$h($value).'</textarea></label></p>';
        }
        if (!array_key_exists('opmerking',$selectedForm['data'])) {
            echo '<p><label>Opmerking <textarea name="fields[opmerking]"></textarea></label></p>';
        }
        echo '<p><label>Reden wijziging <input name="change_reason" required maxlength="500"></label></p><button type="submit">Concept opslaan</button></form>';
    }
    if (getenv('OFFICE_TEST_PROCESS_FORMS_WRITE_ENABLED')==='enabled'
        && in_array($selectedForm['status'],['concept','ingediend','controle'],true)) {
        $actions=['concept'=>['submit_concept'=>'Ter controle indienen'],
                  'ingediend'=>['start_review'=>'Controle starten','return'=>'Terug naar concept'],
                  'controle'=>['reject'=>'Afwijzen','return'=>'Terug naar concept']];
        foreach ($actions[$selectedForm['status']] as $action=>$label) {
            echo '<form method="post" action="/office-test/procesformulieren?project_id='.$h($projectIdRaw).'">';
            echo '<input type="hidden" name="csrf_token" value="'.$h($csrfCookie).'"><input type="hidden" name="action" value="'.$h($action).'">';
            echo '<input type="hidden" name="form_id" value="'.$h($selectedForm['id']).'"><input type="hidden" name="expected_revision" value="'.$h($selectedForm['revision']).'">';
            echo '<label>Motivering <input name="change_reason" required maxlength="500"></label> <button type="submit">'.$h($label).'</button></form>';
        }
    }
    echo '</dl><h3>Revisie- en besluitgeschiedenis</h3>';
    if ($formEvents===[]) echo '<p>Geen geregistreerde gebeurtenissen.</p>';
    else {
        echo '<table><thead><tr><th>Revisie</th><th>Handeling</th><th>Medewerker-ID</th><th>Reden</th><th>Datum</th></tr></thead><tbody>';
        foreach ($formEvents as $event) {
            echo '<tr><td>'.$h($event['revision']).'</td><td>'.$h($event['event_type']).'</td><td>'.$h($event['actor_operator_id']).'</td><td>'.$h($event['reason']).'</td><td>'.$h($event['created_at']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<p>Alleen lezen. Bewerken en goedkeuren zijn nog niet vrijgegeven.</p>';
}
echo '</section></main></body></html>';
