<?php
declare(strict_types=1);

// Test-only, Office-native, single-device revocation.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_ONSITE_DEVICE_REVOKE_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503); exit('Apparaatintrekking niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || !hash_equals('https://' . $allowed, (string)($_SERVER['HTTP_ORIGIN'] ?? ''))) {
  http_response_code(403); exit('Ongeldige herkomst.');
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach (['OnSiteOfficeInvitationCsrf.php','OnSiteOfficeDeviceRevocationAction.php',
    'OnSiteOfficeDeviceManagementRepository.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Inzet/' . $file;
  }
  $secret = getenv('OFFICE_TEST_ONSITE_INVITATION_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) throw new RuntimeException('Missing invitation CSRF secret');
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Office MySQL unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) { http_response_code(401); exit('Sessie verlopen.'); }
  if (!\Brebo\Office\Inzet\OnSiteOfficeInvitationCsrf::verify(
    $_POST['csrf_token'] ?? null, $operator->sessionId, $secret
  )) { http_response_code(403); exit('Ongeldige sessiebevestiging.'); }
  if (($_POST['confirmation'] ?? '') !== 'REVOKE') { http_response_code(403); exit('Intrekking bevestigen.'); }
  $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  $personId = filter_var($_POST['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  if (!is_int($projectId) || !is_int($personId)) { http_response_code(422); exit('Ongeldige medewerker.'); }
  $hash = $_POST['token_hash'] ?? null;
  if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
    http_response_code(422); exit('Ongeldig apparaat.');
  }
  $action = new \Brebo\Office\Inzet\OnSiteOfficeDeviceRevocationAction($db);
  $revoked = $action->revoke($operator->operatorId, $projectId, $personId, $hash);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>Apparaatintrekking · BREBO Office</title>';
  echo '<body><h1>' . ($revoked ? 'Apparaat ingetrokken' : 'Apparaat was al ingetrokken') . '</h1>';
  echo '<p>Alle andere apparaten van deze medewerker blijven gekoppeld.</p>';
  echo '<p><a href="/office-test/projecten?view=inzet">Terug naar Inzet</a></p></body></html>';
} catch (RuntimeException $error) {
  http_response_code(403); exit('Apparaatintrekking niet toegestaan of niet beschikbaar.');
} catch (Throwable $error) {
  error_log('Office OnSite device revocation failed: ' . get_class($error));
  http_response_code(503); exit('Apparaatintrekking tijdelijk niet beschikbaar.');
}
