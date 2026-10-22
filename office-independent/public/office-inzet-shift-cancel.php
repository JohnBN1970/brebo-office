<?php
declare(strict_types=1);
/** Isolated, disabled-by-default cancellation endpoint for Office test. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
  || getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFT_CANCEL_ENABLED') !== 'enabled') {
  http_response_code(503); exit('Annuleren niet beschikbaar.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST'); http_response_code(405); exit('Alleen POST toegestaan.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($host) || !is_string($allowed) || $allowed === ''
  || !hash_equals($allowed,$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || !is_string($origin) || !hash_equals('https://' . $host,$origin)) {
  http_response_code(403); exit('Herkomst niet toegestaan.');
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCancellation.php';
  $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) throw new RuntimeException('CSRF configuration missing');
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator = $provider->currentOperator();
  if ($operator === null) { http_response_code(401); exit('Meld je opnieuw aan.'); }
  if (!\Brebo\Office\Inzet\OfficeInzetSessionCsrf::verify($_POST['csrf_token'] ?? null,$operator->sessionId,$secret)) {
    http_response_code(403); exit('Ongeldig formulier.');
  }
  $id = filter_var($_POST['shift_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if (!is_int($id)) { http_response_code(400); exit('Ongeldige dienst.'); }
  (new \Brebo\Office\Inzet\OfficeInzetShiftCancellation($db))->cancelForOperator($operator->operatorId,$id);
  header('Location: /office-test/projecten?view=inzet&shift_cancelled=' . $id,true,303);
  exit;
} catch (RuntimeException|InvalidArgumentException $e) {
  http_response_code(422); exit('Dienst niet geannuleerd. Controleer je rechten of ververs de planning.');
} catch (Throwable $e) {
  http_response_code(503); exit('Annuleren tijdelijk niet beschikbaar.');
}
