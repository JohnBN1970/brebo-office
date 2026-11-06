<?php
declare(strict_types=1);

// Authenticated, read-only central Documents landing. No file data is exposed
// until document schema and per-context grants have been verified.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
if (getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(503);
    exit('Documenten tijdelijk niet beschikbaar.');
}
try {
    foreach ([
        'OfficeMount.php',
        'WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php',
        'WebsiteOfficeCrmCookieSessionProvider.php',
    ] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
    if (!is_string($dsn) || $dsn === '') {
        throw new RuntimeException('Office database unavailable');
    }
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    ))->currentOperator();
    if ($operator === null) {
        header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
        exit;
    }
    $projectId = null;
    if (isset($_GET['project_id'])) {
        $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($projectId)) {
            http_response_code(400);
            exit('Ongeldig project.');
        }
        require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
        if (!(new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canRead((int)$operator->operatorId, $projectId)) {
            http_response_code(404);
            exit('Dossier niet gevonden.');
        }
    }
    $isNew = ($_SERVER['OFFICE_DOCUMENTS_VIEW'] ?? '') === 'new';
    $base = \Brebo\Office\Intake\OfficeMount::route('documenten',
        $projectId === null ? [] : ['project_id' => $projectId]);
    $new = \Brebo\Office\Intake\OfficeMount::route('documenten/nieuw',
        $projectId === null ? [] : ['project_id' => $projectId]);
    $home = \Brebo\Office\Intake\OfficeMount::route('home');
} catch (Throwable $error) {
    http_response_code(503);
    exit('Documenten tijdelijk niet beschikbaar.');
}
$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Documenten · BREBO Office</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#17283a;font:16px system-ui,sans-serif}
header{background:#17283a;color:#fff;padding:18px clamp(18px,4vw,56px)}
header a{color:#fff;margin-right:24px}main{max-width:1120px;margin:auto;padding:32px 22px}
.top{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
h1{margin:0 0 8px}.muted{color:#536479}.button{display:inline-block;padding:12px 18px;background:#1b5b86;color:#fff;text-decoration:none;border-radius:7px}
section{background:#fff;border:1px solid #dce4ed;border-radius:10px;padding:22px;margin-top:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
.tile{display:block;padding:18px;border:1px solid #dce4ed;border-radius:8px;color:#17283a;text-decoration:none}
.tile:hover{border-color:#1b5b86}details{margin-top:14px}summary{cursor:pointer;font-weight:600}
</style></head><body>
<header><strong>BREBO Office</strong> &nbsp; <a href="<?= $e($home) ?>">Mijn werkplek</a><a href="<?= $e($base) ?>">Documenten</a></header>
<main><div class="top"><div><h1><?= $isNew ? 'Nieuw document' : 'Documenten' ?></h1>
<p class="muted"><?= $projectId === null ? 'Centraal documentbeheer' : 'Documenten binnen het geautoriseerde projectdossier' ?></p></div>
<?php if (!$isNew): ?><a class="button" href="<?= $e($new) ?>">+ Nieuw document</a><?php endif; ?></div>
<?php if ($isNew): ?>
<section><h2>Kies documentsoort</h2><div class="grid">
<?php foreach (['Offerte','Contract','Rapport','Inspectie','Brief','Factuur','Overig document'] as $type): ?>
<div class="tile"><strong><?= $e($type) ?></strong><p class="muted">Invoer wordt beschikbaar na controle van de documentrechten en registratie.</p></div>
<?php endforeach; ?></div>
<p><a href="<?= $e($base) ?>">Terug naar documenten</a></p></section>
<?php else: ?>
<section><h2>Documenten per onderdeel</h2><div class="grid">
<?php foreach (['Projecten','Gebouwen','Klanten en contacten','Personeel','Bedrijfsvoering'] as $type): ?>
<div class="tile"><strong><?= $e($type) ?></strong><p class="muted">Dossiergebonden documenten</p></div>
<?php endforeach; ?></div></section>
<section><h2>Documentenoverzicht</h2><p>De beveiligde documentregistratie wordt hier aangesloten zodra de tabellen en toegangsrechten zijn geverifieerd. Er worden nog geen documenten getoond.</p></section>
<?php endif; ?>
</main></body></html>
