<?php

declare(strict_types=1);

/** Authenticated Calc-to-Office grant validation. Disabled by default. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($path !== '/api/workbench/v1/calculation-grants/validate') {
  http_response_code(404);
  exit(json_encode(['active' => false]));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit(json_encode(['active' => false]));
}
if (getenv('OFFICE_CALC_GRANT_VALIDATION_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit(json_encode(['active' => false]));
}
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($secret) || strlen($secret) < 32 || !is_string($dsn) || $dsn === ''
  || !is_string($user) || !is_string($password)) {
  http_response_code(503);
  exit(json_encode(['active' => false]));
}
$body = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($body) || strlen($body) < 2 || strlen($body) > 4096) {
  http_response_code(400);
  exit(json_encode(['active' => false]));
}
foreach ([
  'OfficeCalcTransportVerifier.php',
  'OfficeCalcRequestReplayGuard.php',
  'OfficeCalcAuthenticatedGrantGate.php',
  'OfficeCalcSessionGrantRepository.php',
  'OfficeCalcGrantReferenceValidator.php',
  'PdoOfficeCalculationPermissionRepository.php',
  'OfficeCalcProjectScopeRepository.php',
  'OfficeCalcActorIdRepository.php',
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
  $gate = new \Brebo\Office\Calculation\OfficeCalcAuthenticatedGrantGate(
    new \Brebo\Office\Calculation\OfficeCalcRequestReplayGuard($db), $secret
  );
  if (!$gate->allow('POST', $path, $body, $headers)) {
    http_response_code(403);
    exit(json_encode(['active' => false]));
  }
  $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
  if (!is_array($data) || array_keys($data) !== ['reference', 'actor_id', 'project_id', 'calculation_id']
    || !is_string($data['reference']) || !preg_match('/^[a-f0-9]{64}$/D', $data['reference'])) {
    http_response_code(400);
    exit(json_encode(['active' => false]));
  }
  foreach (['actor_id', 'project_id', 'calculation_id'] as $field) {
    if (!is_int($data[$field]) || $data[$field] < 1) {
      http_response_code(400);
      exit(json_encode(['active' => false]));
    }
  }
  $validator = new \Brebo\Office\Calculation\OfficeCalcGrantReferenceValidator(
    $db,
    new \Brebo\Office\Calculation\OfficeCalcSessionGrantRepository($db),
    new \Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository($db),
    new \Brebo\Office\Calculation\OfficeCalcProjectScopeRepository($db),
    new \Brebo\Office\Calculation\OfficeCalcActorIdRepository($db),
  );
  echo json_encode(['active' => $validator->isActive(
    $data['reference'], $data['actor_id'], $data['project_id'], $data['calculation_id'], time()
  )], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['active' => false]);
}
