<?php

declare(strict_types=1);

/**
 * Disabled-by-default Office Calc launch API.
 * Registered in the sandbox router only after integration review.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit(json_encode(['error' => 'Methode niet toegestaan.']));
}
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/calc/start') {
  http_response_code(404);
  exit;
}
if (getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit(json_encode(['error' => 'Calc-start niet beschikbaar.']));
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string) $host)
  || !hash_equals('https://' . $allowed, (string) $origin)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit(json_encode(['error' => 'Ongeldige herkomst.']));
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !is_string($password)
  || !is_string($secret) || strlen($secret) < 32) {
  http_response_code(503);
  exit(json_encode(['error' => 'Calc-start niet geconfigureerd.']));
}
$projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$calculationId = filter_var($_POST['calculation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($projectId === false || $calculationId === false) {
  http_response_code(400);
  exit(json_encode(['error' => 'Ongeldige calculatie.']));
}
try {
  foreach ([
    'Intake/WebsiteCrmOperatorContext.php',
    'Intake/WebsiteCrmOperatorSessionProviderInterface.php',
    'Intake/WebsiteOfficeCrmSessionRepository.php',
    'Intake/WebsiteOfficeCrmCookieSessionProvider.php',
    'Calculation/OfficeCalculationPermission.php',
    'Calculation/PdoOfficeCalculationPermissionRepository.php',
    'Calculation/OfficeCalcActorIdRepository.php',
    'Calculation/OfficeCalcProjectScopeRepository.php',
    'Calculation/OfficeCalcLaunchAuthorization.php',
    'Calculation/OfficeCalcLaunchToken.php',
    'Calculation/OfficeCalcLaunchService.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/' . $file;
  }
  $db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $service = new \Brebo\Office\Calculation\OfficeCalcLaunchService(
    $sessions,
    new \Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository($db),
    new \Brebo\Office\Calculation\OfficeCalcActorIdRepository($db),
    new \Brebo\Office\Calculation\OfficeCalcProjectScopeRepository($db),
    $secret,
  );
  $token = $service->issue($projectId, $calculationId);
  echo json_encode(['token' => $token], JSON_THROW_ON_ERROR);
}
catch (DomainException $e) {
  http_response_code(403);
  echo json_encode(['error' => 'Geen toegang tot deze calculatie.']);
}
catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['error' => 'Calc-start tijdelijk niet beschikbaar.']);
}
