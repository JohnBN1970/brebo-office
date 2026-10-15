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
    'OfficeMount.php',
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
    header('Location: ' . (\Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('inloggen') : ($queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen')), true, 302);
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
  // Buildings are scoped to the already authorized project, never to a user-supplied building ID.
  $buildingQuery = $db->prepare(
    'SELECT b.code, b.name, b.address_line, b.postal_code, b.city
     FROM office_project_buildings pb
     INNER JOIN office_buildings b ON b.id = pb.building_id
     WHERE pb.project_id = :project_id
     ORDER BY b.name, b.id LIMIT 100'
  );
  $buildingQuery->execute([':project_id' => $projectId]);
  $buildings = $buildingQuery->fetchAll(PDO::FETCH_ASSOC);
  // Housing types are optional until the reviewed test-only migration is applied.
  $housingTypes = null;
  $schemaCheck = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('office_housing_types', 'office_project_housing_types')");
  if ((int) $schemaCheck->fetchColumn() === 2) {
    $housingQuery = $db->prepare(
      'SELECT b.name AS building_name, ht.code, ht.name, pht.dwelling_count
       FROM office_project_housing_types pht
       INNER JOIN office_housing_types ht ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
       INNER JOIN office_project_buildings pb ON pb.project_id = pht.project_id AND pb.building_id = pht.building_id
       INNER JOIN office_buildings b ON b.id = pb.building_id
       WHERE pht.project_id = :project_id ORDER BY b.name, ht.name LIMIT 200'
    );
    $housingQuery->execute([':project_id' => $projectId]);
    $housingTypes = $housingQuery->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectgegevens tijdelijk niet beschikbaar.');
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
$homeUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('home') : ($queryRouter ? '/index.php?office_route=home' : '/office/home');
header('Content-Type: text/html; charset=utf-8');
$buildingHtml = '<h2>Gekoppelde gebouwen</h2>';
if ($buildings === []) {
  $buildingHtml .= '<p>Er zijn nog geen gebouwen gekoppeld.</p>';
} else {
  $buildingHtml .= '<ul>';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([$building['address_line'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''], static fn($part): bool => is_string($part) && $part !== '')));
    $buildingHtml .= '<li><strong>' . $escape((string) $building['name']) . '</strong> (' . $escape((string) $building['code']) . ')' . ($address !== '' ? ' — ' . $escape($address) : '') . '</li>';
  }
  $buildingHtml .= '</ul>';
}
$housingHtml = '<h2>Woningtypen</h2>';
if ($housingTypes === null) {
  $housingHtml .= '<p>Woningtypen worden beschikbaar na installatie van het gegevensmodel.</p>';
} elseif ($housingTypes === []) {
  $housingHtml .= '<p>Nog geen woningtypen aan dit project toegewezen.</p>';
} else {
  $housingHtml .= '<table><thead><tr><th>Gebouw</th><th>Woningtype</th><th>Aantal woningen</th></tr></thead><tbody>';
  foreach ($housingTypes as $type) {
    $housingHtml .= '<tr><td>' . $escape((string) $type['building_name']) . '</td><td>' . $escape((string) $type['name']) . ' (' . $escape((string) $type['code']) . ')</td><td>' . (int) $type['dwelling_count'] . '</td></tr>';
  }
  $housingHtml .= '</tbody></table>';
}
$statusLabels = ['concept' => 'Concept', 'preparation' => 'Voorbereiding', 'execution' => 'Uitvoering', 'closeout' => 'Oplevering', 'closed' => 'Afgesloten'];
$statusLabel = $statusLabels[$project->status] ?? $project->status;
$moduleHtml = '<h2>Projectwerkbank</h2><div class="modules">';
foreach (['Gebouwen en woningtypen' => 'Gebouwstructuur uitbreiden', 'Calculatie' => 'Projectscope en calculatierechten nog te koppelen', 'Planning' => 'Nog niet aangesloten', 'Uitvoering' => 'Nog niet aangesloten', 'Inkoop' => 'Nog niet aangesloten', 'Financiën' => 'Nog niet aangesloten'] as $module => $detail) {
  $moduleHtml .= '<div class="module"><strong>' . $escape($module) . '</strong><small>' . $escape($detail) . '</small></div>';
}
$moduleHtml .= '</div>';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Project - BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1000px;margin:40px auto;padding:24px}section{background:white;padding:28px;border:1px solid #dbe1e8;border-radius:12px}dt{font-weight:700;margin-top:16px}dd{margin:4px 0}.modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}.module{border:1px solid #dbe1e8;border-radius:10px;padding:18px;background:#f8fafc}.module strong,.module small{display:block}.module small{color:#58697c;margin-top:8px}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #dbe1e8}</style></head><body><main><p><a href="' . $escape($homeUrl) . '">← Mijn werkplek</a></p><section><h1>' . $escape($project->title) . '</h1><p>Projectgegevens uit BREBO Office</p><dl><dt>Projectnummer</dt><dd>' . $project->identity->projectId . '</dd><dt>Code</dt><dd>' . $escape($project->identity->projectCode) . '</dd><dt>Status</dt><dd>' . $escape($statusLabel) . '</dd></dl>' . $buildingHtml . $housingHtml . $moduleHtml . '</section></main></body></html>';
