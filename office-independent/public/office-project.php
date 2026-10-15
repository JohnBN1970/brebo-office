<?php

declare(strict_types=1);

// Native Office project dossier. Never contacts Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Projecten zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string) $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach ([
    'OfficeProjectIdentity.php',
    'OfficeCanonicalProjectContext.php',
    'OfficeNativeProjectRepository.php',
    'OfficeNativeProjectAccess.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Project/' . $file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') throw new RuntimeException('Office database unavailable.');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $sessions->currentOperator();
  if ($operator === null) {
    $queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
    header('Location: ' . ($queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen'), true, 302);
    exit;
  }
  $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if (!is_int($projectId)) {
    http_response_code(400);
    exit('Ongeldig projectnummer.');
  }
  if (!(new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canRead($operator->operatorId, $projectId)) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
  $project = (new \Brebo\Office\Project\OfficeNativeProjectRepository($db))->find($projectId);
  if ($project === null) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectgegevens tijdelijk niet beschikbaar.');
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Project - BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1000px;margin:40px auto;padding:24px}section{background:white;padding:28px;border:1px solid #dbe1e8;border-radius:12px}dt{font-weight:700;margin-top:16px}dd{margin:4px 0}</style></head><body><main><p><a href="/office/home">← Mijn werkplek</a></p><section><h1>' . $escape($project->title) . '</h1><p>Projectgegevens uit BREBO Office</p><dl><dt>Projectnummer</dt><dd>' . $project->identity->projectId . '</dd><dt>Code</dt><dd>' . $escape($project->identity->projectCode) . '</dd><dt>Status</dt><dd>' . $escape($project->status) . '</dd></dl><p>Gebouwen en calculaties worden na verificatie van hun koppelingen toegevoegd.</p></section></main></body></html>';
