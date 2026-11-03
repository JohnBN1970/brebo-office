<?php
declare(strict_types=1);
/** Test-only authorized OnSite hours acceptance; raw presence events stay immutable. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
  || getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_ONSITE_HOURS_REVIEW_ENABLED') !== 'enabled') {
  http_response_code(503); exit('Urenbeoordeling niet beschikbaar.');
}
// Refuse oversized form bodies before session/database work.
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? '0';
if (!is_scalar($contentLength) || !preg_match('/^[0-9]{1,12}$/D', (string)$contentLength)
  || (int)$contentLength > 8192) {
  http_response_code(413); exit('Beoordelingsverzoek te groot.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST'); http_response_code(405); exit('Alleen POST toegestaan.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($host) || !is_string($allowed) || $allowed === ''
  || !hash_equals($allowed, $host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || !is_string($origin) || !hash_equals('https://' . $host, $origin)) {
  http_response_code(403); exit('Herkomst niet toegestaan.');
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php', 'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php', 'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  foreach (['OfficeInzetSessionCsrf.php', 'OnSiteHoursReviewRequest.php', 'OnSiteHoursReviewRepository.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $file;
  }
  $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) throw new RuntimeException('CSRF configuration missing');
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $provider->currentOperator();
  if ($operator === null) { http_response_code(401); exit('Meld je opnieuw aan.'); }
  if (!\Brebo\Office\Inzet\OfficeInzetSessionCsrf::verify($_POST['csrf_token'] ?? null, $operator->sessionId, $secret)) {
    http_response_code(403); exit('Ongeldig formulier.');
  }
  $input = \Brebo\Office\Inzet\OnSiteHoursReviewRequest::parse($_POST);
  (new \Brebo\Office\Inzet\OnSiteHoursReviewRepository($db))->review(
    (int)$operator->operatorId,
    ['project_id'=>$input['project_id'], 'person_id'=>$input['person_id'],
      'in_event_id'=>$input['in_event_id'], 'out_event_id'=>$input['out_event_id']],
    $input['decision'], $input['corrected_seconds'], $input['reason'], $input['request_key']
  );
  header('Location: /office-test/projecten?view=inzet&tab=controle&review_saved=1', true, 303);
  exit;
} catch (InvalidArgumentException $e) {
  http_response_code(400); exit('Ongeldig beoordelingsverzoek.');
} catch (DomainException $e) {
  http_response_code(422); exit('Uren niet beoordeeld: controleer sessie en projectrechten.');
} catch (Throwable $e) {
  http_response_code(503); exit('Urenbeoordeling tijdelijk niet beschikbaar.');
}
