<?php
declare(strict_types=1);

/**
 * Read-only, session-protected Offertestudio preview.
 * No database writes, approval, PDF issuance or financial side effects.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_OFFERTSTUDIO_PREVIEW_ENABLED') !== 'enabled'
    || getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only') {
    http_response_code(503); exit('Offertestudio is nog niet beschikbaar.');
}
$host = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host === ''
    || !hash_equals($host, (string) ($_SERVER['HTTP_HOST'] ?? ''))
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php', 'WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php',
        'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationComposer.php';
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id = ? AND active = 1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) { http_response_code(403); exit('Geen toegang.'); }
} catch (Throwable $e) {
    http_response_code(503); exit('Offertestudio tijdelijk niet beschikbaar.');
}

// Draft persistence is deliberately disabled until the schema is verified.
$storageReady = getenv('OFFICE_QUOTATIONS_SCHEMA_VERIFIED') === 'enabled'
    && getenv('OFFICE_QUOTATIONS_WRITE_ENABLED') === 'enabled';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_QUOTATION_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['quotation_csrf'])) $_SESSION['quotation_csrf'] = bin2hex(random_bytes(32));
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!is_string($_POST['csrf'] ?? null)
        || !hash_equals($_SESSION['quotation_csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    if (!$storageReady) { http_response_code(503); exit('Opslaan is nog niet geactiveerd.'); }
    $relationId = filter_var($_POST['relation_id'] ?? null, FILTER_VALIDATE_INT);
    $postedType = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
    $sectionValues = $_POST['sections'] ?? null;
    if (!$relationId || !is_array($sectionValues) || !array_key_exists($postedType, \\Brebo\\Office\\Quotation\\OfficeQuotationComposer::TEMPLATES)) {
        http_response_code(400); exit('Ongeldige offertegegevens.');
    }
    try {
        require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationDraftRepository.php';
        $id = (new \\Brebo\\Office\\Quotation\\OfficeQuotationDraftRepository($db))
            ->create((int)$relationId, $operator->operatorId, $postedType, $title, $sectionValues);
        $_SESSION['quotation_csrf'] = bin2hex(random_bytes(32));
        header('Location: /office-test/crm/offertes?type=' . rawurlencode($postedType) . '&created=' . $id, true, 303);
        exit;
    } catch (\\InvalidArgumentException $e) {
        $message = 'Controleer de relatie, titel en hoofdstukteksten.';
    } catch (\\Throwable $e) {
        http_response_code(503); exit('Conceptofferte kon niet worden opgeslagen.');
    }
}
$relations = [];
if ($storageReady) {
    try {
        $relations = $db->query("SELECT id, display_name FROM office_crm_relations WHERE status = 'active' ORDER BY display_name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\\Throwable $e) { http_response_code(503); exit('Relaties niet beschikbaar.'); }
}

$type = (string) ($_GET['type'] ?? 'project');
if (!array_key_exists($type, \Brebo\Office\Quotation\OfficeQuotationComposer::TEMPLATES)) $type = 'project';
$labels = [
    'project' => 'Uitgebreide projectofferte',
    'mjop' => 'MJOP / advies',
    'regie' => 'Regievoorstel',
    'compact' => 'Compacte offerte',
];
$sectionLabels = [
    'intro' => 'Introductie', 'scope' => 'Werkzaamheden',
    'method' => 'Uitvoeringsmethode', 'planning' => 'Planning',
    'price' => 'Prijs en calculatie', 'terms' => 'Termijnschema',
    'references' => 'Referentieprojecten', 'attachments' => 'Bijlagen',
    'conditions' => 'Voorwaarden', 'deliverables' => 'Op te leveren producten',
    'rates' => 'Regietarieven',
];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offertestudio · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f3f5fa;color:#172c43;margin:0}main{max-width:1100px;margin:36px auto;padding:20px}header,.panel{background:white;border:1px solid #dce3ed;border-radius:12px;padding:24px;margin-bottom:18px}.types{display:flex;flex-wrap:wrap;gap:10px}.types a{padding:12px 16px;border-radius:8px;background:#e9eef9;color:#173d70;text-decoration:none}.types a.selected{background:#273f83;color:white}.sections{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.section{background:#fff;border:1px solid #dce3ed;border-radius:10px;padding:18px}.section p{color:#5d6d80}small{color:#65748a}a{color:#175a91}input,select,textarea{display:block;width:100%;box-sizing:border-box;padding:10px;margin-top:6px;border:1px solid #aebbc9;border-radius:6px}button{padding:12px 20px;border:0;border-radius:7px;background:#273f83;color:white}</style></head><body><main><p><a href="/office-test/crm/verkoopkansen">Terug naar CRM</a></p><header><small>BREBO OFFICE / OFFERTES</small><h1>Offertestudio</h1><p>Werkbare demonstratie van documenttypen en hoofdstukopbouw. Conceptopslag is alleen beschikbaar na schema- en schrijfvrijgave. Calculatiekoppeling, PDF en verzending zijn nog niet actief.</p><nav class="types">';
foreach ($labels as $key => $label) {
    echo '<a class="'.($key === $type ? 'selected' : '').'" href="?type='.$escape($key).'">'.$escape($label).'</a>';
}
echo '</nav></header><section class="panel"><h2>'.$escape($labels[$type]).'</h2><div class="sections">';
foreach (\Brebo\Office\Quotation\OfficeQuotationComposer::sections($type) as $index => $key) {
    echo '<article class="section"><small>HOOFDSTUK '.($index + 1).'</small><h3>'.$escape($sectionLabels[$key] ?? $key).'</h3><p>Inhoud wordt later gekoppeld aan CRM, calculatie en de documentbibliotheek.</p></article>';
}
echo '</div></section>';
if ($message !== '') echo '<p role="alert">'.$escape($message).'</p>';
if (isset($_GET['created']) && ctype_digit((string)$_GET['created'])) echo '<p role="status">Conceptofferte aangemaakt. Nummer: '.(int)$_GET['created'].'</p>';
if ($storageReady) {
    echo '<section class="panel"><h2>Nieuw offerteconcept</h2><form method="post" action="/office-test/crm/offertes?type='.$escape($type).'"><input type="hidden" name="csrf" value="'.$escape($_SESSION['quotation_csrf']).'"><input type="hidden" name="type" value="'.$escape($type).'"><p><label>Relatie <select name="relation_id" required><option value="">Kies een relatie</option>';
    foreach ($relations as $relation) echo '<option value="'.(int)$relation['id'].'">'.$escape((string)$relation['display_name']).'</option>';
    echo '</select></label></p><p><label>Offertetitel <input name="title" maxlength="255" required></label></p>';
    foreach (\\Brebo\\Office\\Quotation\\OfficeQuotationComposer::sections($type) as $key) {
        echo '<p><label>'.$escape($sectionLabels[$key] ?? $key).'<textarea name="sections['.$escape($key).']" rows="4" maxlength="50000"></textarea></label></p>';
    }
    echo '<button type="submit">Concept opslaan</button></form></section>';
} else {
    echo '<section class="panel"><h2>Concepteditor</h2><p>Opslaan is nog niet vrijgegeven. Hiervoor zijn een gecontroleerde database-installatie en de twee offerteflags vereist.</p></section>';
}
echo '</main></body></html>';
