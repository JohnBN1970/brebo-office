<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeLoginController;
use Brebo\Office\Intake\WebsiteOfficeLoginCsrf;
use Brebo\Office\Intake\WebsiteOfficeLoginFormRenderer;
use Brebo\Office\Intake\WebsiteOfficeLoginThrottle;
use Brebo\Office\Intake\WebsiteOfficeOperatorAuthenticator;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionIssuer;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginController.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginCsrf.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginFormRenderer.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginThrottle.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeOperatorAuthenticator.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled' || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503);
  exit('Office-aanmelding is nog niet beschikbaar.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: GET, POST');
  http_response_code(405);
  exit('Methode niet toegestaan.');
}
$now = time();
$csrf = $_COOKIE['office_login_csrf'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  $csrf = WebsiteOfficeLoginCsrf::issue();
  setcookie('office_login_csrf', $csrf, WebsiteOfficeLoginCsrf::cookieOptions($now + 900));
  header('Content-Type: text/html; charset=utf-8');
  echo (new WebsiteOfficeLoginFormRenderer())->render($csrf);
  exit;
}
if (!WebsiteOfficeLoginCsrf::verify($_POST['csrf_token'] ?? null, $csrf)) {
  http_response_code(403);
  exit('Ongeldig aanmeldformulier. Vernieuw de pagina.');
}
if (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null)) {
  http_response_code(400);
  exit('Ongeldige aanmeldgegevens.');
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!$dsn) {
  http_response_code(503);
  exit('Office-aanmelding is nog niet beschikbaar.');
}
try {
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $throttle = new WebsiteOfficeLoginThrottle($db);
  $username = $_POST['username'];
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
  $identity = 'account:' . strtolower(trim($username));
  $ipIdentity = 'ip:' . (is_string($clientIp) ? $clientIp : 'unknown');
  if (!$throttle->allowed($identity, $now) || !$throttle->allowed($ipIdentity, $now)) {
    http_response_code(429);
    header('Retry-After: 900');
    exit('Te veel aanmeldpogingen. Probeer het later opnieuw.');
  }
  $controller = new WebsiteOfficeLoginController(new WebsiteOfficeOperatorAuthenticator($db), new WebsiteOfficeCrmSessionIssuer($db));
  $token = $controller->login($username, $_POST['password'], $now);
  if ($token === null) {
    $throttle->failed($identity, $now);
    $throttle->failed($ipIdentity, $now);
  }
  else {
    $throttle->reset($identity);
  }
}
catch (Throwable $exception) {
  error_log('Office login unavailable: ' . get_class($exception));
  http_response_code(503);
  exit('Office-aanmelding is tijdelijk niet beschikbaar.');
}
if ($token === null) {
  http_response_code(401);
  $newCsrf = WebsiteOfficeLoginCsrf::issue();
  setcookie('office_login_csrf', $newCsrf, WebsiteOfficeLoginCsrf::cookieOptions($now + 900));
  header('Content-Type: text/html; charset=utf-8');
  echo (new WebsiteOfficeLoginFormRenderer())->render($newCsrf, true);
  exit;
}
setcookie('office_login_csrf', '', WebsiteOfficeLoginCsrf::cookieOptions($now - 3600));
setcookie('office_session', $token, WebsiteOfficeLoginController::cookie($token, $now + 28800));
header('Location: /office/crm/aanvragen', true, 303);
