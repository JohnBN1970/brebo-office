<?php

declare(strict_types=1);

// Test-only Office personnel-number POST entrypoint. Routing must be explicitly
// registered by the deployment configuration before this endpoint is accessible.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Personeelsbeheer niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($origin) || !hash_equals('https://' . $allowedHost, $origin)) {
  http_response_code(403);
  exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach (['OfficeInzetPersonnelProfileCsrf.php','OfficeInzetPersonnelProfileRepository.php',
    'OfficeInzetPersonnelProfileAction.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $file;
  }
  $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) throw new RuntimeException('Security configuration missing');
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(401);
    exit('Sessie verlopen.');
  }
  $action = new \Brebo\Office\Inzet\OfficeInzetPersonnelProfileAction(
    new \Brebo\Office\Inzet\OfficeInzetPersonnelProfileRepository($db));
  if (!$action->update($_POST, $operator->operatorId, $operator->sessionId, $secret)) {
    http_response_code(403);
    exit('Geen wijzigingsrechten of medewerker niet gevonden.');
  }
  $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  $personId = filter_var($_POST['person_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if (!is_int($projectId) || !is_int($personId)) throw new InvalidArgumentException('Invalid profile context');
  header('Location: /office-test/projecten?view=inzet&profile_project=' . $projectId
    . '&profile_person=' . $personId . '&profile_saved=1', true, 303);
  exit;
} catch (InvalidArgumentException|DomainException $exception) {
  http_response_code(422);
  exit('Ongeldige profielwijziging.');
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Profielwijziging tijdelijk niet beschikbaar.');
}
