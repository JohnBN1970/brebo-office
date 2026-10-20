<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$deny = static function(int $status, string $code): never {
  http_response_code($status);
  echo json_encode(['error'=>$code]);
  exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  $deny(405, 'method_not_allowed');
}
if (getenv('OFFICE_ONSITE_ACTIVATION_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled') $deny(503, 'onsite_not_enabled');
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowed) || $allowed === ''
  || !hash_equals($allowed, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403, 'invalid_origin');
$type = $_SERVER['CONTENT_TYPE'] ?? '';
if (!is_string($type) || !preg_match('/^application\/json(?:\s*;|$)/i', $type)) $deny(415, 'json_required');
$body = file_get_contents('php://input', false, null, 0, 2049);
if (!is_string($body) || strlen($body) > 2048) $deny(413, 'payload_too_large');
try { $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR); }
catch (Throwable) { $deny(400, 'invalid_json'); }
if (!is_array($payload) || !is_string($payload['activation_token'] ?? null)) $deny(400, 'invalid_activation_token');
try {
  foreach (['OnSiteDeviceTokenRepository','OnSiteActivationService'] as $name) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $name . '.php';
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Office MySQL unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $service = new \Brebo\Office\Inzet\OnSiteActivationService($db, new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));
  $result = $service->activate($payload['activation_token']);
  http_response_code($result['status']);
  echo json_encode($result['body'], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
  error_log('Office OnSite activation failed: ' . get_class($error));
  $deny(503, 'activation_unavailable');
}
