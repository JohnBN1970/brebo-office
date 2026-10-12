<?php

declare(strict_types=1);

/**
 * Independent, read-only Office CRM route.
 * Deployment prerequisite: a trusted reverse proxy MUST authenticate the Office
 * operator, strip inbound token headers and inject the server-side token.
 * Not enabled by any existing production router.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/office/crm/aanvragen') {
  http_response_code(404);
  exit;
}
$expected = getenv('OFFICE_CRM_WORKBENCH_TOKEN');
$provided = $_SERVER['HTTP_X_OFFICE_CRM_WORKBENCH_TOKEN'] ?? null;
if (!is_string($expected) || strlen($expected) < 32 || !is_string($provided) || !hash_equals($expected, $provided)) {
  http_response_code(403);
  exit;
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !is_string($password)) {
  http_response_code(503);
  exit;
}
try {
  require_once dirname(__DIR__) . '/src/Intake/WebsiteWebsiteOpportunityReadRepository.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteWebsiteOpportunityListRenderer.php';
  $db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $rows = (new \Brebo\Office\Intake\WebsiteWebsiteOpportunityReadRepository($db))->recent(50);
  echo (new \Brebo\Office\Intake\WebsiteWebsiteOpportunityListRenderer())->render($rows);
}
catch (Throwable) {
  http_response_code(503);
  echo 'Het overzicht van nieuwe aanvragen is tijdelijk niet beschikbaar.';
}
