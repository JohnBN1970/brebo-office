<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('Leadregistratie niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
              'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
    $sources = $db->query('SELECT id,label FROM office_crm_lead_sources WHERE active=1 ORDER BY label')
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_LEAD_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_lead_csrf'])) $_SESSION['crm_lead_csrf'] = bin2hex(random_bytes(32));
$h = static fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';
$values = ['source_id'=>'','title'=>'','source_detail'=>'','campaign'=>'','contact_name'=>'','contact_email'=>'','description'=>''];
if ($method === 'POST') {
    if (getenv('OFFICE_CRM_LEADS_WRITE_ENABLED') !== 'enabled') {
        http_response_code(403); exit('Leadregistratie schrijven niet ingeschakeld.');
    }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['crm_lead_csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    foreach ($values as $key => $_) $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    $sourceId = filter_var($values['source_id'], FILTER_VALIDATE_INT) ?: 0;
    $validSource = false;
    foreach ($sources as $source) if ((int)$source['id'] === $sourceId) $validSource = true;
    if (!$validSource || $values['title'] === '' || mb_strlen($values['title'],'UTF-8') > 255
        || mb_strlen($values['source_detail'],'UTF-8') > 255
        || mb_strlen($values['campaign'],'UTF-8') > 255
        || mb_strlen($values['contact_name'],'UTF-8') > 255
        || mb_strlen($values['description'],'UTF-8') > 10000
        || ($values['contact_email'] !== '' && (!filter_var($values['contact_email'],FILTER_VALIDATE_EMAIL)
            || strlen($values['contact_email']) > 254))) {
        $error = 'Controleer de verplichte bron, titel en overige invoervelden.';
    } else {
        try {
            // Match only an exact CRM email. Ambiguous identities require manual review.
            $matches = [];
            if ($values['contact_email'] !== '') {
                $match = $db->prepare("SELECT id,display_name FROM office_crm_relations
                    WHERE status='active' AND LOWER(email)=LOWER(?) LIMIT 2");
                $match->execute([$values['contact_email']]);
                $matches = $match->fetchAll(PDO::FETCH_ASSOC);
            }
            if (count($matches) > 1) {
                $error = 'Meerdere relaties gevonden. Controleer de klant handmatig via Relaties.';
            } elseif (count($matches) === 1) {
                $error = 'Bestaande relatie gevonden: '.$matches[0]['display_name'].'. Maak hiervoor rechtstreeks een verkoopkans aan via Relaties.';
            }
            if ($error !== '') {
                // Existing customer must not be recorded as a new lead.
                throw new \DomainException($error);
            }
            $db->beginTransaction();
            $insert = $db->prepare("INSERT INTO office_crm_leads
                (source_id,title,source_detail,campaign,contact_name,contact_email,description,stage,status)
                VALUES (?,?,?,?,?,?,?,'new','active')");
            $insert->execute([$sourceId,$values['title'],$values['source_detail'] ?: null,
                $values['campaign'] ?: null,$values['contact_name'] ?: null,
                $values['contact_email'] ?: null,$values['description'] ?: null]);
            $leadId = (int)$db->lastInsertId();
            $event = $db->prepare("INSERT INTO office_crm_lead_events (lead_id,actor_operator_id,event_type,new_stage)
                VALUES (?,?,'created','new')");
            $event->execute([$leadId,(string)$operator->operatorId]);
            $db->commit();
            $_SESSION['crm_lead_csrf'] = bin2hex(random_bytes(32));
            header('Location: /office-test/crm/aanvragen/detail?id='.$leadId, true, 303); exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (!$e instanceof \DomainException) $error = 'Lead opslaan mislukt.';
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nieuwe lead · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:760px;margin:32px auto;padding:20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:16px 0}input,select,textarea{display:block;width:100%;box-sizing:border-box;padding:10px}button{padding:12px 20px;background:#175a91;color:white;border:0;border-radius:6px}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/aanvragen">Terug naar aanvragen</a></p><h1>Nieuwe lead</h1><section>';
if ($error !== '') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_lead_csrf']).'"><label>Herkomstbron *<select name="source_id" required><option value="">Kies een bron</option>';
foreach ($sources as $source) echo '<option value="'.(int)$source['id'].'"'.((string)$source['id'] === $values['source_id'] ? ' selected' : '').'>'.$h($source['label']).'</option>';
echo '</select></label>';
foreach (['title'=>'Aanvraag *','source_detail'=>'Specificatie bron','campaign'=>'Campagne','contact_name'=>'Contactpersoon','contact_email'=>'E-mailadres'] as $key=>$label) {
    $type = $key === 'contact_email' ? 'email' : 'text';
    echo '<label>'.$h($label).'<input type="'.$type.'" name="'.$h($key).'" maxlength="'.($key === 'contact_email' ? '254' : '255').'" value="'.$h($values[$key]).'"'.($key === 'title' ? ' required' : '').'></label>';
}
echo '<label>Omschrijving<textarea name="description" rows="5" maxlength="10000">'.$h($values['description']).'</textarea></label>';
if (getenv('OFFICE_CRM_LEADS_WRITE_ENABLED') === 'enabled') echo '<button type="submit">Lead registreren</button>';
else echo '<p>Registratie is nog niet vrijgegeven.</p>';
echo '</form></section></main></body></html>';
