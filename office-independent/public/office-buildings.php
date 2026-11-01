<?php
declare(strict_types=1);

// Read-only buildings linked to projects visible to the current Office operator.
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
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Gebouwen zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
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
  $statement = $db->prepare(
    "SELECT b.id, b.name AS building_name, b.address_line AS street_address, b.postal_code, b.city,
            COUNT(DISTINCT pb.project_id) AS project_count,
            COUNT(DISTINCT CASE WHEN z.active = 1 THEN z.id END) AS active_zone_count
       FROM office_buildings b
       INNER JOIN office_project_buildings pb ON pb.building_id = b.id
       INNER JOIN office_project_memberships m ON m.project_id = pb.project_id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id AND a.active = 1
       LEFT JOIN office_building_zones z ON z.building_id = b.id
      WHERE m.operator_id = ?
        AND m.permission IN ('read','manage')
      GROUP BY b.id, b.name, b.address_line, b.postal_code, b.city
      ORDER BY b.name, b.id LIMIT 200"
  );
  $statement->execute([$operator->operatorId]);
  $buildings = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Gebouwenoverzicht tijdelijk niet beschikbaar.');
}
$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebouwen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1100px;margin:32px auto;padding:24px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dbe1e8}a{color:#175a91}@media(max-width:650px){section{overflow-x:auto}}</style></head><body><main><h1>Gebouwen</h1><p>Gebouwen gekoppeld aan projecten waarvoor je toegang hebt.</p><section>';
if ($buildings === []) {
  echo '<p>Geen toegankelijke gebouwen gevonden.</p>';
} else {
  echo '<table><thead><tr><th>Gebouw</th><th>Adres</th><th>Projecten</th><th>Actieve zones</th></tr></thead><tbody>';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([
      $building['street_address'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''
    ], static fn($part) => is_string($part) && trim($part) !== '')));
    echo '<tr><td>' . $h($building['building_name']) . '</td><td>' . $h($address) .
      '</td><td>' . $h($building['project_count']) . '</td><td>' . $h($building['active_zone_count']) . '</td></tr>';
  }
  echo '</tbody></table>';
}
echo '</section></main></body></html>';
