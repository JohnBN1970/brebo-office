<?php

declare(strict_types=1);

/**
 * Independent Office intake front controller.
 *
 * Deploy behind an HTTPS reverse proxy mapping
 * /brebo-internal/intake/europakozijn to this script.
 * Never run database migrations on an incoming request.
 */
use Brebo\Office\Intake\WebsiteIntakeHandler;
use Brebo\Office\Intake\WebsiteIntakeHttpEndpoint;
use Brebo\Office\Intake\WebsiteIntakeSignatureVerifier;
use Brebo\Office\Intake\WebsiteIntakeStore;
use Brebo\Office\Intake\WebsiteIntakeValidator;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($method !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  echo json_encode(['status' => 'error', 'error' => ['code' => 'method_not_allowed']]);
  exit;
}
if ($path !== '/brebo-internal/intake/europakozijn') {
  http_response_code(404);
  echo json_encode(['status' => 'error', 'error' => ['code' => 'not_found']]);
  exit;
}

$rawBody = file_get_contents('php://input', false, null, 0, 64001);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 64000) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'error' => ['code' => 'invalid_request']]);
  exit;
}

$secret = getenv('BREBO_SHARED_SECRET');
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($secret) || $secret === '' || !is_string($dsn) || $dsn === ''
  || !is_string($user) || !is_string($password)) {
  http_response_code(503);
  echo json_encode(['status' => 'error', 'error' => ['code' => 'intake_unavailable']]);
  exit;
}

try {
  foreach ([
    'WebsiteLeadRepositoryInterface', 'WebsiteOpportunityMapper', 'WebsiteIntakeStore',
    'WebsiteIntakeValidator', 'WebsiteIntakeHandler', 'WebsiteIntakeSignatureVerifier',
    'WebsiteIntakeHttpEndpoint',
  ] as $class) {
    require_once dirname(__DIR__) . '/src/Intake/' . $class . '.php';
  }
  $pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $endpoint = new WebsiteIntakeHttpEndpoint(
    new WebsiteIntakeSignatureVerifier($secret),
    new WebsiteIntakeHandler(new WebsiteIntakeValidator(), new WebsiteIntakeStore($pdo)),
  );
  $headers = [];
  foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_') && is_string($value)) {
      $headers[str_replace('_', '-', substr($key, 5))] = $value;
    }
  }
  $result = $endpoint->dispatch($method, $path, $rawBody, $headers, time());
  http_response_code($result['status']);
  echo json_encode($result['body'], JSON_THROW_ON_ERROR);
}
catch (Throwable) {
  http_response_code(503);
  echo json_encode(['status' => 'error', 'error' => ['code' => 'intake_unavailable']]);
}
