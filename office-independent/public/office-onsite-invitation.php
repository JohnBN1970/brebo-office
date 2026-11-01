<?php
declare(strict_types=1);

// Explicitly routed, test-only Office OnSite invitation issuance.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED') !== 'enabled'
  || getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503); exit('Activatie-uitgifte niet beschikbaar.');
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
  foreach (['OnSiteOfficeInvitationCsrf.php','OnSiteActivationInvitationIssuer.php',
    'OnSiteOfficeInvitationAction.php'] as $file) {
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
  if (($_POST['confirmation'] ?? '') !== 'ISSUE') { http_response_code(403); exit('Bevestiging vereist.'); }
  $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  $personId = filter_var($_POST['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  if (!is_int($projectId) || !is_int($personId)) { http_response_code(422); exit('Ongeldige medewerker.'); }
  $action = new \Brebo\Office\Inzet\OnSiteOfficeInvitationAction(
    $db, new \Brebo\Office\Inzet\OnSiteActivationInvitationIssuer($db)
  );
  $invitation = $action->issue($operator->operatorId, $projectId, $personId);
  require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeActivationPage.php';
  header('Content-Type: text/html; charset=utf-8');
  echo \Brebo\Office\Inzet\OnSiteOfficeActivationPage::issued($invitation);
} catch (RuntimeException $error) {
  http_response_code(403); exit('Activatie niet toegestaan of niet beschikbaar.');
} catch (Throwable $error) {
  error_log('Office OnSite invitation issue failed: ' . get_class($error));
  http_response_code(503); exit('Activatie tijdelijk niet beschikbaar.');
}
