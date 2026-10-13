<?php

declare(strict_types=1);

/**
 * Server-to-server Calc project-context endpoint.
 * Intentionally not routed or enabled until authoritative Office project data
 * and the sandbox routing contract are reviewed.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($path) || !preg_match('#^/api/workbench/v1/projects/([1-9][0-9]*)/calculation-context$#D', $path, $matches)) {
  http_response_code(404);
  exit(json_encode(['error' => 'Niet gevonden.']));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit(json_encode(['error' => 'Methode niet toegestaan.']));
}
if (getenv('OFFICE_CALC_CONTEXT_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit(json_encode(['error' => 'Projectcontext niet beschikbaar.']));
}
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($secret) || strlen($secret) < 32 || !is_string($dsn) || $dsn === ''
  || !is_string($user) || !is_string($password)) {
  http_response_code(503);
  exit(json_encode(['error' => 'Projectcontext niet geconfigureerd.']));
}
foreach ([
  'OfficeCalcTransportVerifier.php',
  'OfficeCalcRequestReplayGuard.php',
  'OfficeCalcAuthenticatedContextGate.php',
  'OfficeCalcProjectContextRepository.php',
] as $file) {
  require_once dirname(__DIR__) . '/src/Calculation/' . $file;
}
try {
  $db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  if (!is_array($headers)) {
    $headers = [];
  }
  foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_') && is_string($value)) {
      $headers[str_replace('_', '-', substr($key, 5))] = $value;
    }
  }
  $gate = new \\Brebo\\Office\\Calculation\\OfficeCalcAuthenticatedContextGate(
    new \\Brebo\\Office\\Calculation\\OfficeCalcRequestReplayGuard($db),
    $secret,
  );
  if (!$gate->allow('GET', $path, '', $headers)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Geen toegang.']));
  }
  $context = (new \\Brebo\\Office\\Calculation\\OfficeCalcProjectContextRepository($db))->find((int) $matches[1]);
  if ($context === null) {
    http_response_code(404);
    exit(json_encode(['error' => 'Project niet gevonden.']));
  }
  echo json_encode($context, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['error' => 'Projectcontext tijdelijk niet beschikbaar.']);
}
