<?php
declare(strict_types=1);
// Office-native read-only device enrollment console. No public enrollment.
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
if (getenv('OFFICE_ONSITE_ADMIN_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503);
  exit('OnSite-beheer is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed,(string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn,'mysql:')) throw new RuntimeException('Office database unavailable');
  $db = new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(401);
    exit('Meld je aan bij Office.');
  }
  $grant = $db->prepare('SELECT 1 FROM onsite_enrollment_operator_grants WHERE operator_id = ? AND active = 1 LIMIT 1');
  $grant->execute([$operator->operatorId]);
  if ($grant->fetchColumn() === false) {
    http_response_code(403);
    exit('Geen toegang tot apparaatbeheer.');
  }
  $employees = $db->query('SELECT e.employee_id,e.display_name,COUNT(t.token_hash) AS device_count
    FROM onsite_employees e
    LEFT JOIN onsite_device_tokens t ON t.employee_id = e.employee_id AND t.revoked_at IS NULL
    WHERE e.active = 1
    GROUP BY e.employee_id,e.display_name ORDER BY e.display_name LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('OnSite device console unavailable: '.get_class($e));
  http_response_code(503);
  exit('OnSite-beheer is tijdelijk niet beschikbaar.');
}
header('Content-Type: text/html; charset=utf-8');
$h = static fn(string $s): string => htmlspecialchars($s,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1><title>Apparaatbeheer · BREBO Office</title><style>body{font:16px system-ui;background:#f4f6fa;color:#172b42;margin:0;padding:2rem}main{max-width:900px;margin:auto;background:white;border-radius:14px;padding:2rem}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #ddd}p{color:#536174}</style></head><body><main><h1>OnSite · Apparaatbeheer</h1><p>Alleen bevoegde Office-gebruikers. Apparaten worden niet automatisch gekoppeld.</p><table><thead><tr><th>Medewerker</th><th>Medewerker-ID</th><th>Actieve apparaten</th></tr></thead><tbody>';
foreach ($employees as $employee) {
  echo '<tr><td>'.$h((string)$employee['display_name']).'</td><td>'.$h((string)$employee['employee_id']).'</td><td>'.(int)$employee['device_count'].'</td></tr>';
}
echo '</tbody></table><p>Uitgifte van activatiecodes wordt pas ingeschakeld na afzonderlijke beveiligingscontrole.</p></main></body></html>';
