<?php
declare(strict_types=1);

/**
 * Office-native OnSite presence HTTP boundary.
 * Fail closed until the Office device and assignment adapters are installed.
 * Never accept an employee ID or project authorization from the request body.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}
if (getenv('OFFICE_ONSITE_PRESENCE_ENABLED') !== 'enabled') {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'onsite_not_enabled']);
  exit;
}
// Explicitly reject requests until a trusted Office assignment provider
// and device-link lifecycle have been wired to OnSitePresenceRequestService.
http_response_code(503);
echo json_encode(['ok' => false, 'error' => 'onsite_integration_incomplete']);
