<?php

declare(strict_types=1);

// Read-only native Office project portfolio, isolated from Drupal.
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
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
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
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),
    $_COOKIE,
    time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $statement = $db->prepare(
    "SELECT p.id, p.code, p.title, p.status, COUNT(DISTINCT pb.building_id) AS building_count
     FROM office_projects p
     INNER JOIN office_project_memberships m ON m.project_id = p.id
     INNER JOIN office_operator_accounts a ON a.id = m.operator_id
     LEFT JOIN office_project_buildings pb ON pb.project_id = p.id
     WHERE m.operator_id = :operator_id AND m.permission IN ('read','manage') AND a.active = 1
     GROUP BY p.id, p.code, p.title, p.status
     ORDER BY p.id DESC LIMIT 100"
  );
  $statement->execute([':operator_id' => $operator->operatorId]);
  $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectoverzicht tijdelijk niet beschikbaar.');
}
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = \Brebo\Office\Intake\OfficeMount::basePath();
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Projecten · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}main{max-width:1100px;margin:40px auto;padding:0 24px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:14px;border-bottom:1px solid #dbe1e8}a{color:#175a91}h1{margin-top:0}@media(max-width:600px){table{display:block;overflow-x:auto}}</style></head><body><main><p><a href="' . $escape($base . '/') . '">← Mijn werkplek</a></p><section><h1>Projecten</h1><p>Projecten waarvoor je toegang hebt.</p>';
if ($projects === []) {
  echo '<p>Er zijn nog geen toegankelijke projecten.</p>';
} else {
  echo '<table><thead><tr><th>Projectcode</th><th>Project</th><th>Status</th><th>Gebouwen</th></tr></thead><tbody>';
  foreach ($projects as $project) {
    $url = $base . '/projecten/detail?id=' . (int) $project['id'];
    echo '<tr><td>' . $escape($project['code']) . '</td><td><a href="' . $escape($url) . '">' . $escape($project['title']) . '</a></td><td>' . $escape($project['status']) . '</td><td>' . (int) $project['building_count'] . '</td></tr>';
  }
  echo '</tbody></table>';
}
echo '</section></main></body></html>';
