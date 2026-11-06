<?php
declare(strict_types=1);

// Standalone Office documents landing page; no Drupal or public file access.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_DOCUMENTS_UI_ENABLED') !== 'enabled') {
    http_response_code(503);
    exit('Documentenmodule is nog niet geactiveerd.');
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
    if (!is_string($dsn) || $dsn === '') throw new RuntimeException('database_not_configured');
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    );
    $operator = $sessions->currentOperator();
    if ($operator === null) {
        header('Location: /office-test/inloggen', true, 302);
        exit;
    }
    $required = ['office_document_originals', 'office_documents', 'office_document_contexts', 'office_document_sources'];
    $schema = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'office_document%'");
    $available = $schema->fetchAll(PDO::FETCH_COLUMN);
    $ready = count(array_diff($required, $available)) === 0;
} catch (Throwable $error) {
    error_log('office_documents_landing_failed: ' . get_class($error));
    http_response_code(503);
    exit('Documenten tijdelijk niet beschikbaar.');
}
function docHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Documenten - BREBO Office</title>
<style>
body{margin:0;background:#f3f6f9;color:#1c3041;font:16px system-ui,Arial,sans-serif}
main{max-width:1060px;margin:35px auto;padding:0 22px}
header{display:flex;justify-content:space-between;align-items:center;gap:20px}
h1{font-size:30px;margin-bottom:8px}p{line-height:1.5}
a{color:#155782;text-decoration:none}
.card{background:white;border:1px solid #dbe3eb;border-radius:12px;padding:25px;margin-top:22px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-top:18px}
.tile{border:1px solid #dce5ec;border-radius:9px;padding:20px}
small{color:#5c7180}
.notice{background:#fff6e5;border-left:4px solid #b7832c;padding:16px;margin-top:20px}
</style></head><body><main>
<header><strong>BREBO OFFICE</strong><a href="/office-test/projecten">Terug naar projecten</a></header>
<h1>Documenten</h1><p>Centraal documentbeheer voor projecten, gebouwen, klanten, personeel en bedrijfsvoering.</p>
<div class="card"><h2>Documentenoverzicht</h2>
<?php if (!$ready): ?>
<div class="notice">De documenttabellen zijn nog niet geïnstalleerd in Office-test. Uploaden en downloaden zijn daarom nog niet beschikbaar.</div>
<?php else: ?>
<div class="notice">De documentregistratie is aanwezig. De gebruikersinterface voor uploaden en downloaden wordt pas vrijgegeven na autorisatie- en acceptatietests.</div>
<?php endif; ?>
<div class="grid">
<div class="tile"><strong>Projectdocumenten</strong><p><small>Rapporten, tekeningen, offertes en contracten</small></p><a href="/office-test/projecten">Open projecten</a></div>
<div class="tile"><strong>Gebouwdocumenten</strong><p><small>Technische en onderhoudsdocumentatie</small></p></div>
<div class="tile"><strong>CRM-documenten</strong><p><small>Klantgebonden correspondentie en overeenkomsten</small></p></div>
<div class="tile"><strong>Bedrijfsvoering en personeel</strong><p><small>Interne documenten en personeelsdossiers</small></p></div>
</div></div>
</main></body></html>
