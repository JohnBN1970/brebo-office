<?php
declare(strict_types=1);

/**
 * Session-protected Offertestudio draft editor; writes require explicit flags.
 * No database writes, approval, PDF issuance or financial side effects.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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

// The Office session context stores the operator ID as a string; quotation ownership uses BIGINT.
// Validate the canonical numeric identity before passing it to strict-typed repository methods.
$operatorIdRaw = $operator->operatorId;
if (!ctype_digit($operatorIdRaw) || $operatorIdRaw === '0' || strlen($operatorIdRaw) > 18) {
    error_log('OFFERTSTUDIO_OPERATOR_ID_NOT_NUMERIC');
    http_response_code(403); exit('Gebruikersidentiteit niet geschikt voor Offertestudio.');
}
$quotationOperatorId = (int) $operatorIdRaw;

// Draft persistence is deliberately disabled until the schema is verified.
$storageReady = getenv('OFFICE_QUOTATIONS_SCHEMA_VERIFIED') === 'enabled'
    && getenv('OFFICE_QUOTATIONS_WRITE_ENABLED') === 'enabled';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_QUOTATION_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['quotation_csrf'])) $_SESSION['quotation_csrf'] = bin2hex(random_bytes(32));
require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationDraftRepository.php';
$draftRepository = new \Brebo\Office\Quotation\OfficeQuotationDraftRepository($db);
$editingId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$editing = null;
if ($storageReady && $editingId > 0) {
    try { $editing = $draftRepository->getOwnDraft($editingId, $quotationOperatorId); }
    catch (\Throwable $e) { http_response_code(503); exit('Concept tijdelijk niet beschikbaar.'); }
    if ($editing === null) { http_response_code(404); exit('Concept niet gevonden.'); }
}
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
    if (count($_POST) > 30) { http_response_code(400); exit('Te veel formuliervelden.'); }
    if (!$relationId || !is_array($sectionValues) || count($sectionValues) > 20 || !array_key_exists($postedType, \Brebo\Office\Quotation\OfficeQuotationComposer::TEMPLATES)) {
        http_response_code(400); exit('Ongeldige offertegegevens.');
    }
    try {
        require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationDraftRepository.php';
        $editId = filter_var($_POST['quotation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if (isset($_POST['quotation_id']) && (string)$_POST['quotation_id'] !== '0' && !$editId) {
            http_response_code(400); exit('Ongeldig conceptnummer.');
        }
        if ($editId > 0) {
            $existing = $draftRepository->getOwnDraft($editId, $quotationOperatorId);
            if (!$existing || $existing['quotation_type'] !== $postedType
                || (int)$existing['relation_id'] !== (int)$relationId
                ) {
                http_response_code(403); exit('Concept niet toegankelijk.');
            }
            $draftRepository->updateOwnDraft($editId, $quotationOperatorId, $title, $sectionValues);
            $id = $editId;
        } else {
            $id = $draftRepository->create((int)$relationId, $quotationOperatorId, $postedType, $title, $sectionValues);
        }
        $_SESSION['quotation_csrf'] = bin2hex(random_bytes(32));
        header('Location: /office-test/crm/offertes?type=' . rawurlencode($postedType) . '&edit=' . $id . '&saved=1', true, 303);
        exit;
    } catch (\InvalidArgumentException $e) {
        $message = 'Controleer de relatie, titel en hoofdstukteksten.';
    } catch (\Throwable $e) {
        http_response_code(503); exit('Conceptofferte kon niet worden opgeslagen.');
    }
}
$relations = [];
$ownDrafts = [];
if ($storageReady) {
    try {
        $relations = $db->query("SELECT id, display_name FROM office_crm_relations WHERE status = 'active' ORDER BY display_name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { error_log('OFFERTSTUDIO_RELATIONS_QUERY_FAILED: '.get_class($e)); http_response_code(503); exit('CRM-relaties konden niet worden geladen.'); }
    try {
        $ownDrafts = $draftRepository->listOwn($quotationOperatorId);
    } catch (\Throwable $e) {
        $rawCode = (string) $e->getCode();
        $code = $e instanceof \PDOException && preg_match('/^(?:[A-Z0-9]{5}|[0-9]{1,5})$/D', $rawCode) ? $rawCode : 'UNKNOWN';
        $driverCode = $e instanceof \PDOException && isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1]) ? (string) (int) $e->errorInfo[1] : '';
        error_log('OFFERTSTUDIO_DRAFT_LIST_FAILED class='.get_class($e).' code='.$code.' driver='.$driverCode);
        if ($code === 'UNKNOWN' && $driverCode !== '') $code = $driverCode;
        http_response_code(503);
        exit('Offerteconcepten konden niet worden geladen (code '.$code.').');
    }
}

$type = $editing ? (string)$editing['quotation_type'] : (string) ($_GET['type'] ?? 'project');
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
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offertestudio · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f3f5fa;color:#172c43;margin:0}main{max-width:1540px;margin:20px auto;padding:20px;display:grid;grid-template-columns:minmax(210px,240px) minmax(0,1fr) minmax(210px,260px);gap:16px;align-items:start}main>p,main>header{grid-column:1/-1}main>.chapter-nav{grid-column:1;grid-row:3;position:sticky;top:16px}main>.draft-editor{grid-column:2;grid-row:3;min-width:0}main>.draft-list{grid-column:3;grid-row:3;position:sticky;top:16px;min-width:0}main>.print-document{grid-column:2;grid-row:auto;min-width:0}main>.status-message{grid-column:2}.sections{display:block}.section{margin-bottom:10px;min-width:0;box-sizing:border-box}.section p{display:none}.chapter-nav{min-width:0}.chapter-nav .section{padding:10px}.chapter-nav .section h3{font-size:14px;margin:4px 0;overflow-wrap:anywhere}.chapter-nav .section small{font-size:10px}.draft-editor textarea{min-height:110px;resize:vertical}.draft-editor form>p{margin-bottom:16px}@media(max-width:1100px){main{grid-template-columns:minmax(180px,220px) minmax(0,1fr)}main>.draft-list{grid-column:2;grid-row:auto;position:static}main>.chapter-nav{grid-column:1;grid-row:3;position:static}}@media(max-width:720px){main{display:block;margin:0;padding:12px}main>.chapter-nav,main>.draft-list{position:static}.sections{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}}header,.panel{background:white;border:1px solid #dce3ed;border-radius:12px;padding:24px;margin-bottom:18px}.types{display:flex;flex-wrap:wrap;gap:10px}.types a{padding:12px 16px;border-radius:8px;background:#e9eef9;color:#173d70;text-decoration:none}.types a.selected{background:#273f83;color:white}.chapter-nav .sections{display:flex;flex-direction:column;gap:10px;min-width:0}.chapter-nav .section{width:100%;min-width:0;max-width:100%;box-sizing:border-box;margin:0}.chapter-nav .section a{display:block;overflow-wrap:anywhere}.chapter-nav{overflow:hidden}.section{background:#fff;border:1px solid #dce3ed;border-radius:10px;padding:18px}.section p{color:#5d6d80}small{color:#65748a}a{color:#175a91}input,select,textarea{display:block;width:100%;box-sizing:border-box;padding:10px;margin-top:6px;border:1px solid #aebbc9;border-radius:6px}button{padding:12px 20px;border:0;border-radius:7px;background:#273f83;color:white}.print-chapter{break-inside:avoid;margin:20px 0}.print-chapter p{white-space:normal;line-height:1.6}@media print{body{background:#fff}main{margin:0;max-width:none;padding:0}main>p,header,.panel:not(.print-document){display:none}.print-document{border:0;padding:0}.print-document button{display:none}}</style></head><body><main><p><a href="/office-test/crm/verkoopkansen">Terug naar CRM</a></p><header><small>BREBO OFFICE / OFFERTES</small><h1>Offertestudio</h1><p>Maak en bewerk offerteconcepten. Hoofdstukken links, documenteditor centraal en concepten rechts. Goedkeuring, verzending en definitieve PDF-uitgifte zijn nog niet actief.</p><nav class="types">';
foreach ($labels as $key => $label) {
    echo '<a class="'.($key === $type ? 'selected' : '').'" href="?type='.$escape($key).'">'.$escape($label).'</a>';
}
echo '</nav></header>';
if ($editing !== null) {
    echo '<section class="panel print-document" id="documentvoorbeeld"><small>CONCEPT · NIET VERZONDEN</small><h2>'.$escape((string)$editing['title']).'</h2>';
    foreach (\Brebo\Office\Quotation\OfficeQuotationComposer::sections($type) as $chapter) {
        $body = trim((string)($editing['sections'][$chapter] ?? ''));
        if ($body === '') continue;
        echo '<article class="print-chapter"><h3>'.$escape($sectionLabels[$chapter] ?? $chapter).'</h3><p>'.nl2br($escape($body), false).'</p></article>';
    }
    echo '<p><small>Dit document is uitsluitend een conceptvoorbeeld en geen goedgekeurde of verzonden offerte.</small></p>';
    echo '<button type="button" onclick="window.print()">Documentvoorbeeld afdrukken / bewaren als PDF</button></section>';
}
echo '<section class="panel chapter-nav"><h2>Hoofdstukken · '.$escape($labels[$type]).'</h2><div class="sections">';
foreach (\Brebo\Office\Quotation\OfficeQuotationComposer::sections($type) as $index => $key) {
    echo '<article class="section"><small>HOOFDSTUK '.($index + 1).'</small><h3><a href="#section-'.$escape($key).'">'.$escape($sectionLabels[$key] ?? $key).'</a></h3></article>';
}
echo '</div></section>';
if ($message !== '') echo '<p role="alert">'.$escape($message).'</p>';
if ($editing && isset($_GET['saved'])) echo '<p role="status">Concept opgeslagen.</p>';
if ($storageReady) {
    echo '<section class="panel draft-editor" id="offerte-editor"><h2>'.($editing ? 'Concept bewerken' : 'Nieuw offerteconcept').'</h2><form method="post" action="/office-test/crm/offertes?type='.$escape($type).'"><input type="hidden" name="csrf" value="'.$escape($_SESSION['quotation_csrf']).'"><input type="hidden" name="type" value="'.$escape($type).'"><p><label>Relatie <select name="relation_id" required><option value="">Kies een relatie</option>';
    foreach ($relations as $relation) echo '<option value="'.(int)$relation['id'].'"'.($editing && (int)$editing['relation_id'] === (int)$relation['id'] ? ' selected' : '').'>'.$escape((string)$relation['display_name']).'</option>';
    echo '</select></label></p><p><label>Offertetitel <input name="title" maxlength="255" required value="'.($editing ? $escape((string)$editing['title']) : '').'"></label></p>';
    foreach (\Brebo\Office\Quotation\OfficeQuotationComposer::sections($type) as $key) {
        echo '<p id="section-'.$escape($key).'"><label>'.$escape($sectionLabels[$key] ?? $key).'<textarea name="sections['.$escape($key).']" rows="4" maxlength="50000">'.($editing ? $escape((string)($editing['sections'][$key] ?? '')) : '').'</textarea></label></p>';
    }
    echo '<input type="hidden" name="quotation_id" value="'.($editing ? (int)$editing['id'] : 0).'">';
    echo '<button type="submit">Concept opslaan</button></form></section><section class="panel draft-list"><h2>Mijn conceptoffertes</h2><ul>';
    foreach ($ownDrafts as $draft) echo '<li><a href="/office-test/crm/offertes?edit='.(int)$draft['id'].'">'.$escape((string)$draft['title']).'</a> — '.$escape((string)$draft['relation_name']).'</li>';
    echo '</ul></section>';
} else {
    echo '<section class="panel"><h2>Concepteditor</h2><p>Opslaan is nog niet vrijgegeven. Hiervoor zijn een gecontroleerde database-installatie en de twee offerteflags vereist.</p></section>';
}
echo <<<'HTML'
<script>
(function () {
  'use strict';
  const editor = document.querySelector('.draft-editor');
  const navigation = document.querySelector('.chapter-nav');
  if (!editor || !navigation) return;
  const sections = Array.from(editor.querySelectorAll('p[id^="section-"]'));
  const links = Array.from(navigation.querySelectorAll('a[href^="#section-"]'));
  if (!sections.length) return;
  const bar = document.createElement('div');
  bar.className = 'chapter-toolbar';
  bar.style.cssText = 'display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:14px 0;padding:12px;background:#eef2fa;border-radius:8px';
  const name = document.createElement('strong');
  const counter = document.createElement('small');
  const controls = document.createElement('div');
  controls.style.cssText = 'display:flex;gap:8px';
  const previous = document.createElement('button');
  const next = document.createElement('button');
  previous.type = next.type = 'button';
  previous.textContent = 'Vorige'; next.textContent = 'Volgende';
  controls.append(previous, next);
  bar.append(name, counter, controls);
  const form = editor.querySelector('form');
  if (!form) return;
  form.insertBefore(bar, sections[0]);
  let active = 0;
  function show(index, focus) {
    active = Math.max(0, Math.min(index, sections.length - 1));
    sections.forEach((section, i) => { section.hidden = i !== active; section.style.display = i === active ? '' : 'none'; });
    links.forEach(link => {
      const selected = link.getAttribute('href') === '#' + sections[active].id;
      link.setAttribute('aria-current', selected ? 'step' : 'false');
      link.style.fontWeight = selected ? '800' : '400';
    });
    const label = sections[active].querySelector('label');
    name.textContent = label ? label.childNodes[0].textContent.trim() : 'Hoofdstuk';
    counter.textContent = 'Hoofdstuk ' + (active + 1) + ' van ' + sections.length;
    previous.disabled = active === 0;
    next.disabled = active === sections.length - 1;
    if (focus) sections[active].querySelector('textarea')?.focus();
  }
  links.forEach(link => link.addEventListener('click', event => {
    const index = sections.findIndex(section => '#' + section.id === link.getAttribute('href'));
    if (index < 0) return;
    event.preventDefault(); show(index, true);
  }));
  previous.addEventListener('click', () => show(active - 1, true));
  next.addEventListener('click', () => show(active + 1, true));
  show(0, false);
})();
</script>
HTML;
echo '</main></body></html>';
