<?php
declare(strict_types=1);

/**
 * Central Office document landing page.
 * Inspection documents remain in their existing authorized repository.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
    http_response_code(404);
    exit;
}
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    exit;
}
try {
    foreach ([
        'OfficeMount.php', 'WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php', 'WebsiteOfficeCrmCookieSessionProvider.php'
    ] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    );
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(503);
    exit('Documenten tijdelijk niet beschikbaar.');
}
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (array_key_exists('project_id', $_GET) && !is_int($projectId)) {
    http_response_code(400);
    exit('Ongeldig projectnummer.');
}
if (is_int($projectId)) {
    try {
        require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';

        $access = new \Brebo\Office\Project\OfficeNativeProjectAccess($db);

        $operatorId = filter_var(
            $operator->operatorId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!is_int($operatorId) || !$access->canRead($operatorId, $projectId)) {
            http_response_code(404);
            exit('Project niet gevonden.');
        }
    } catch (\Throwable $error) {
        http_response_code(503);
        exit('Projectdocumenten tijdelijk niet beschikbaar.');
    }
}
$documentScope = is_int($projectId) ? ['project_id' => $projectId] : [];
$inspectionUrl = '/office-test/inspecties' . (is_int($projectId) ? '?project_id=' . $projectId : '');
require_once dirname(__DIR__) . '/src/Documents/OfficeNewDocumentMenu.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Documenten - BREBO Office</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}
main{max-width:1050px;margin:32px auto;padding:0 20px}
header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
h1{margin:0}section{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:24px;margin:20px 0}
a{color:#1c5f8d}.muted{color:#5d6b79}.office-new-document{position:relative}
.office-new-document summary{cursor:pointer;background:#1d587c;color:#fff;border-radius:8px;padding:12px 16px}
.office-new-document ul{list-style:none;padding:8px;margin:6px 0;background:#fff;border:1px solid #dce3eb;border-radius:8px;min-width:280px}
.office-new-document li{padding:7px}.office-new-document span[aria-disabled]{color:#697685}
</style></head><body><main>
<header><div><h1>Documenten</h1><p class="muted">Centraal toegangspunt voor documenten in BREBO Office.</p></div>
<?php
try {
    echo \Brebo\Office\Documents\OfficeNewDocumentMenu::render('documenten', $documentScope);
} catch (\Throwable $error) {
    echo '<p>Documentmenu tijdelijk niet beschikbaar.</p>';
}
?>
</header>
<section class="office-document-context-navigation" aria-label="Documenten per onderdeel">
<h2>Documenten per onderdeel</h2>
<p>Open documenten vanuit het bijbehorende onderdeel. Bestaande rechten en projectafscherming blijven van toepassing.</p>
<nav aria-label="Documentcontexten" style="display:flex;flex-wrap:wrap;gap:12px">
<a href="/office-test/projecten">Projecten</a>
<a href="/office-test/crm/relaties">Klanten en relaties</a>
<a href="/office-test/gebouwen">Gebouwen</a>
<a href="/office-test/personeel/medewerkers">Personeel</a>
<a href="/office-test/finance">Bedrijfsvoering</a>
</nav></section>
<section><h2>Documentenoverzicht</h2>
<p>De bestaande inspectierapporten zijn beschikbaar via het beveiligde inspectieoverzicht.<?= is_int($projectId) ? " Projectfilter: " . (int) $projectId . "." : "" ?></p>
<p><a href="<?= htmlspecialchars($inspectionUrl, ENT_QUOTES, "UTF-8") ?>">Inspectierapporten bekijken</a></p>
<p class="muted">Andere documentsoorten worden hier zichtbaar zodra hun centrale registratie en autorisatie zijn aangesloten.</p>
</section></main></body></html>
