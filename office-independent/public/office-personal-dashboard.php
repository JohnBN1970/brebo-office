<?php
declare(strict_types=1);

// Isolated Office personal-dashboard entrypoint. Not mounted until router review.
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
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_PERSONAL_DASHBOARD_ENABLED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503);
  exit('Persoonlijk dashboard nog niet beschikbaar.');
}
$expectedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($expectedHost) || $expectedHost === ''
  || !hash_equals($expectedHost, (string)($_SERVER['HTTP_HOST'] ?? ''))) {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'OfficeMount.php', 'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') throw new RuntimeException('Database unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $sessions->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id = :id AND active = 1 LIMIT 1');
  $active->execute([':id' => $operator->operatorId]);
  if ($active->fetchColumn() === false) {
    http_response_code(403);
    exit('Geen Office-toegang.');
  }
} catch (Throwable $e) {
  http_response_code(503);
  exit('Dashboard tijdelijk niet beschikbaar.');
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$home = \Brebo\Office\Intake\OfficeMount::route('home');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mijn dashboard · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#182b40;font:16px system-ui,sans-serif}main{max-width:1160px;margin:32px auto;padding:20px}.panel{background:white;border:1px solid #e1e6ec;border-radius:12px;padding:24px}a{color:#176196}</style></head><body><main><p><a href="'.$escape($home).'">Terug naar Office</a></p><div class="panel"><h1>Mijn dashboard</h1><p>Persoonlijke widgets worden hier beschikbaar zodra de bronmodules, rechten en database-migratie zijn geverifieerd.</p><p>Er worden geen voorbeeld-KPI’s als werkelijke bedrijfsgegevens getoond.</p></div></main></body></html>';
