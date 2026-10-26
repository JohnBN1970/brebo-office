<?php
declare(strict_types=1);

/**
 * Isolated Office-test zone creation endpoint.
 * Requires verified Office project management, same-session CSRF and an explicit test-only feature gate.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit;
}
$host = $_SERVER['HTTP_HOST'] ?? '';
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_ZONE_WRITE_ENABLED') !== 'enabled'
  || !is_string($host) || $host === '' || !hash_equals((string) getenv('OFFICE_PUBLIC_HOST'), $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503);
  exit('Zoneopslag niet beschikbaar.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($origin) || !hash_equals('https://' . $host, $origin)) {
  http_response_code(403);
  exit('Ongeldige herkomst.');
}
$token = $_POST['csrf'] ?? null;
$cookie = $_COOKIE['office_zone_csrf'] ?? null;
if (!is_string($token) || !is_string($cookie) || strlen($token) !== 64
  || !ctype_xdigit($token) || !hash_equals($cookie, $token)) {
  http_response_code(403);
  exit('Ongeldige formulierbeveiliging.');
}
$projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($projectId)) {
  http_response_code(400);
  exit('Ongeldig project.');
}
try {
  foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingRepository.php';
  require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingZoneRepository.php';
  require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || getenv('OFFICE_INTAKE_DB_USER') !== 'u213420663_office_test') {
    throw new RuntimeException('missing_test_database');
  }
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(403);
    exit('Niet aangemeld.');
  }
  $access = new \Brebo\Office\Project\OfficeNativeProjectAccess($db);
  $authorized = static fn(string $id): bool => $id === (string) $projectId
    && $access->canManage($operator->operatorId, $projectId);
  if (!$authorized((string) $projectId)) {
    http_response_code(403);
    exit('Geen projectbeheerrechten.');
  }
  $buildingId = $_POST['building_id'] ?? null;
  if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId)) {
    throw new InvalidArgumentException('invalid_building_id');
  }
  $buildings = new \Brebo\Office\Buildings\OfficeBuildingRepository($db);
  $authorizedBuilding = static fn(string $id): bool => $authorized((string) $projectId)
    && $buildings->belongsToProject((string) $projectId, $id);
  if (!$authorizedBuilding($buildingId)) {
    http_response_code(403);
    exit('Gebouw hoort niet bij dit project.');
  }
  $zone = [
    'building_id' => $buildingId,
    'zone_name' => $_POST['zone_name'] ?? null,
    'latitude' => $_POST['latitude'] ?? null,
    'longitude' => $_POST['longitude'] ?? null,
    'radius_metres' => $_POST['radius_metres'] ?? null,
  ];
  $creator = new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db);
  $requestKey = $_POST['request_key'] ?? null;
  if (!is_string($requestKey) || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
    throw new InvalidArgumentException('invalid_zone_request_key');
  }
  $zoneId = $creator->createOnce($zone, $requestKey, $authorizedBuilding);
  header('Location: /office-test/inzet/projectkaart?id=' . $projectId . '&building_id=' . rawurlencode($buildingId) . '&zone_created=' . rawurlencode($zoneId), true, 303);
  exit;
} catch (InvalidArgumentException $e) {
  http_response_code(400);
  exit('Ongeldige zonegegevens.');
} catch (Throwable $e) {
  http_response_code(503);
  exit('Zone kon niet worden opgeslagen.');
}
