<?php

declare(strict_types=1);

/**
 * Internal, read-only CRM migration workbench.
 * Must additionally be restricted to trusted operators at the reverse proxy.
 * Never expose this file as a public route without authentication.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'; frame-ancestors \'none\'');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
$route = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($route !== '/brebo-internal/crm/migration' && $route !== '/brebo-internal/crm/migration/detail') {
  http_response_code(404);
  exit;
}

$expected = getenv('OFFICE_CRM_WORKBENCH_TOKEN');
$provided = $_SERVER['HTTP_X_OFFICE_CRM_WORKBENCH_TOKEN'] ?? null;
if (!is_string($expected) || strlen($expected) < 32 || !is_string($provided)
  || !hash_equals($expected, $provided)) {
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
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmLegacyReadRepository.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmLegacyWorkbenchRenderer.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmLegacyDetailRenderer.php';
  $db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $repository = new \Brebo\Office\Intake\WebsiteCrmLegacyReadRepository($db);
  if ($route === '/brebo-internal/crm/migration/detail') {
    $rawId = $_GET['id'] ?? null;
    if (!is_string($rawId) || !ctype_digit($rawId) || strlen($rawId) > 18 || (int) $rawId < 1) {
      http_response_code(400);
      echo 'Ongeldig kansnummer.';
      exit;
    }
    $html = (new \Brebo\Office\Intake\WebsiteCrmLegacyDetailRenderer())->render($repository, (int) $rawId);
    if ($html === null) {
      http_response_code(404);
      echo 'Verkoopkans niet gevonden.';
      exit;
    }
    echo $html;
  }
  else {
    echo (new \Brebo\Office\Intake\WebsiteCrmLegacyWorkbenchRenderer())->render($repository);
  }
}
catch (Throwable) {
  http_response_code(503);
  echo 'CRM-migratiecontrole tijdelijk niet beschikbaar.';
}
