<?php
declare(strict_types=1);

/**
 * Independent Office OnSite presence HTTP endpoint.
 * Explicit enablement requires a separately verified schema and device
 * enrollment. Never provisions tables or accepts caller-supplied employee IDs.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$respond = static function (int $status, string $error): never {
  http_response_code($status);
  echo json_encode(['ok' => false, 'error' => $error]);
  exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  $respond(405, 'method_not_allowed');
}
if (getenv('OFFICE_ONSITE_PRESENCE_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled') {
  $respond(503, 'onsite_not_enabled');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowedHost) || $allowedHost === ''
  || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  $respond(403, 'invalid_origin');
}
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!is_string($contentType) || !preg_match('#^application/json(?:\s*;\s*charset=utf-8)?\s*$#iD', $contentType)) {
  $respond(415, 'json_required');
}
$length = $_SERVER['CONTENT_LENGTH'] ?? '';
if ($length !== '' && (!ctype_digit((string)$length) || (int)$length > 8192)) {
  $respond(413, 'payload_too_large');
}
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!is_string($header) || !preg_match('/^Bearer ([A-Za-z0-9._~+\/-]{32,512})$/D', $header, $match)) {
  $respond(401, 'device_not_linked');
}
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($raw) || strlen($raw) > 8192) $respond(413, 'payload_too_large');
$payload = json_decode($raw, true);
if (!is_array($payload) || array_is_list($payload)) $respond(400, 'invalid_json');
try {
  foreach ([
    'OnSiteAutomaticEventValidator', 'OnSiteEventInbox', 'OnSiteAutomaticIngest',
    'OnSiteDeviceTokenRepository', 'OnSiteAssignmentRepository',
    'OnSiteAssignmentGate', 'OnSiteOfficeTeamAssignmentRepository',
    'OnSiteAuthenticatedEventService', 'OnSitePresenceRequestService',
    'OnSitePresenceApiHandler', 'OnSitePresenceApplication',
    'OnSiteCanonicalZoneRepository',
  ] as $name) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $name . '.php';
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
    throw new RuntimeException('Office MySQL not configured');
  }
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  // Projection is only safe once a trusted Drupal sync and project identity mapping
  // have been explicitly verified. Otherwise keep the endpoint fail-closed.
  if (getenv('OFFICE_ONSITE_ZONE_SYNC_VERIFIED') !== 'enabled'
    || getenv('OFFICE_ONSITE_PROJECT_MAPPING_VERIFIED') !== 'enabled') {
    throw new RuntimeException('OnSite canonical zone source not verified');
  }
  $zones = new \\Brebo\\Office\\Inzet\\OnSiteCanonicalZoneRepository($db);
  $result = (new \\Brebo\\Office\\Inzet\\OnSitePresenceApplication($db, $zones->callback()))
    ->submit($payload, $match[1]);
  http_response_code($result['status']);
  echo json_encode($result['body']);
} catch (Throwable $error) {
  error_log('Office OnSite presence failed: ' . get_class($error));
  $respond(503, 'onsite_unavailable');
}
