<?php
declare(strict_types=1);
/**
 * Test-only Office Inzet shift POST endpoint. No production mount.
 * Writes are disabled unless every independent release gate is enabled.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
  || getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit('Dienstinvoer niet beschikbaar.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit('Alleen POST toegestaan.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($host) || !is_string($allowed) || $allowed === ''
  || !hash_equals($allowed,$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || !is_string($origin) || !hash_equals('https://' . $host,$origin)) {
  http_response_code(403);
  exit('Herkomst niet toegestaan.');
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  foreach ([
    'OfficeInzetShiftAssignmentControl.php',
    'OfficeInzetShiftWriter.php',
    'OfficeInzetShiftCommand.php',
    'OfficeInzetLocalTime.php',
    'OfficeInzetShiftSubmission.php',
    'OfficeInzetShiftCsrf.php',
    'OfficeInzetSessionCsrf.php',
    'OfficeInzetShiftPostHandler.php',
    'OfficeInzetAuthenticatedShiftAction.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Inzet/' . $file;
  $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) throw new RuntimeException('Shift CSRF configuration missing');
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
  );
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(401);
    exit('Meld je opnieuw aan.');
  }
  $action = new \Brebo\Office\Inzet\OfficeInzetAuthenticatedShiftAction(
    new \Brebo\Office\Inzet\OfficeInzetShiftPostHandler(
      new \Brebo\Office\Inzet\OfficeInzetShiftSubmission(
        new \Brebo\Office\Inzet\OfficeInzetShiftCommand($db,new \Brebo\Office\Inzet\OfficeInzetShiftWriter($db))
      )
    )
  );
  $id = $action->handle('POST',$operator,$_POST,$secret);
  header('Location: /office-test/projecten?view=inzet&shift_saved=' . $id,true,303);
  exit;
} catch (InvalidArgumentException|RuntimeException $e) {
  http_response_code(422);
  exit('Dienst niet opgeslagen. Controleer de medewerker, tijden en projectrechten.');
} catch (Throwable $e) {
  http_response_code(503);
  exit('Dienstinvoer tijdelijk niet beschikbaar.');
}
