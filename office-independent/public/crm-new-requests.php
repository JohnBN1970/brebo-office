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
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
$route = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($route !== '/office/crm/aanvragen' && $route !== '/office/crm/aanvragen/detail') {
  http_response_code(404);
  exit;
}
$expected = getenv('OFFICE_CRM_WORKBENCH_TOKEN');
$provided = $_SERVER['HTTP_X_OFFICE_CRM_WORKBENCH_TOKEN'] ?? null;
if (!is_string($expected) || strlen($expected) < 32 || !is_string($provided) || !hash_equals($expected, $provided)) {
  http_response_code(403);
  exit;
}
// An Office-owned session is mandatory in addition to the trusted proxy gate.
// Do not activate this mode until Office manages the session table and login.
$sessionMode = getenv('OFFICE_CRM_SESSION_MODE');
if ($sessionMode !== 'enabled') {
  http_response_code(503);
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
  require_once dirname(__DIR__) . '/src/Intake/WebsiteWebsiteOpportunityDetailRenderer.php';
  $db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorSessionProviderInterface.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmSessionRepository.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmCookieSessionProvider.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmAccessPolicy.php';
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),
    $_COOKIE,
    time(),
  );
  try {
    (new \Brebo\Office\Intake\WebsiteOfficeCrmAccessPolicy($provider))->requireReader();
  }
  catch (DomainException) {
    http_response_code(403);
    exit;
  }
  $repository = new \Brebo\Office\Intake\WebsiteWebsiteOpportunityReadRepository($db);
  if ($route === '/office/crm/aanvragen/detail') {
    $rawId = $_GET['id'] ?? null;
    if (!is_string($rawId) || !ctype_digit($rawId) || strlen($rawId) > 18 || (int) $rawId < 1) {
      http_response_code(400);
      echo 'Ongeldig verkoopkansnummer.';
      exit;
    }
    $opportunity = $repository->find((int) $rawId);
    if ($opportunity === null) {
      http_response_code(404);
      echo 'Verkoopkans niet gevonden.';
      exit;
    }
    echo (new \Brebo\Office\Intake\WebsiteWebsiteOpportunityDetailRenderer())->render($opportunity);
  }
  else {
    $pageRaw = $_GET['page'] ?? '1';
    if (!is_string($pageRaw) || !ctype_digit($pageRaw) || strlen($pageRaw) > 5 || (int) $pageRaw < 1 || (int) $pageRaw > 20001) {
      http_response_code(400);
      echo 'Ongeldig paginanummer.';
      exit;
    }
    $page = (int) $pageRaw;
    $rows = $repository->recent(51, ($page - 1) * 50);
    $hasNextPage = count($rows) > 50;
    echo (new \Brebo\Office\Intake\WebsiteWebsiteOpportunityListRenderer())->render(array_slice($rows, 0, 50), $page, $hasNextPage);
  }
}
catch (Throwable) {
  http_response_code(503);
  echo 'Het overzicht van nieuwe aanvragen is tijdelijk niet beschikbaar.';
}
