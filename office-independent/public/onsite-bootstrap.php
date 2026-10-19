<?php
declare(strict_types=1);
/**
 * Device-authenticated independent Office bootstrap.
 * Feature remains disabled until the isolated schema and verified employee,
 * project and zone projections are provisioned by Office administrators.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$deny = static function(int $status, string $code): never {
  http_response_code($status);
  echo json_encode(['ok'=>false,'error'=>$code]);
  exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  $deny(405, 'method_not_allowed');
}
if (getenv('OFFICE_ONSITE_BOOTSTRAP_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled') {
  $deny(503, 'onsite_not_enabled');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowed) || $allowed === ''
  || !hash_equals($allowed, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  $deny(403, 'invalid_origin');
}
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!is_string($header) || !preg_match('/^Bearer ([A-Za-z0-9._~+\/-]{32,512})$/D', $header, $match)) {
  $deny(401, 'device_not_linked');
}
try {
  foreach (['OnSiteDeviceTokenRepository','OnSiteBootstrapRepository','OnSiteBootstrapService'] as $name) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $name . '.php';
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Office MySQL unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  if (getenv('OFFICE_ONSITE_ZONE_SYNC_VERIFIED') !== 'enabled'
    || getenv('OFFICE_ONSITE_PROJECT_MAPPING_VERIFIED') !== 'enabled') {
    throw new RuntimeException('OnSite project zones not verified');
  }
  $service = new \Brebo\Office\Inzet\OnSiteBootstrapService(
    new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db)
  );
  $repository = new \Brebo\Office\Inzet\OnSiteBootstrapRepository($db);
  $result = $service->bootstrap($match[1], static fn(string $employeeId): array => $repository->forEmployee($employeeId));
  http_response_code($result['status']);
  echo json_encode($result['body'], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
  error_log('Office OnSite bootstrap failed: ' . get_class($error));
  $deny(503, 'bootstrap_unavailable');
}
