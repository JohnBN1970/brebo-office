<?php

declare(strict_types=1);

/**
 * Office-owned Calc entry gate. Must be explicitly registered by the public
 * Office router after sandbox verification. Does not proxy Calc yet.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/calc-werkbank') {
  http_response_code(404);
  exit;
}
if (getenv('OFFICE_CALC_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit('Calculatie is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string) $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
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
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorSessionProviderInterface.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmSessionRepository.php';
  require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmCookieSessionProvider.php';
  require_once dirname(__DIR__) . '/src/Calculation/OfficeCalculationPermission.php';
  require_once dirname(__DIR__) . '/src/Calculation/PdoOfficeCalculationPermissionRepository.php';
  require_once dirname(__DIR__) . '/src/Calculation/OfficeCalculationSessionAccess.php';
  $db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  if ($sessions->currentOperator() === null) {
    header('Location: /inloggen', true, 302);
    exit;
  }
  (new \Brebo\Office\Calculation\OfficeCalculationSessionAccess(
    $sessions, new \Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository($db)
  ))->requireCalculator();
}
catch (DomainException $e) {
  http_response_code(403);
  exit('Geen toegang tot calculatie.');
}
catch (Throwable $e) {
  http_response_code(503);
  exit('Calculatie tijdelijk niet beschikbaar.');
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>BREBO Calculatie</title><h1>Calculatie</h1><p>Toegang bevestigd. De Calc-werkbank wordt hier gekoppeld.</p></html>';
