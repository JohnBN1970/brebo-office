<?php

declare(strict_types=1);

use Brebo\Office\Intake\OfficeMount;
use Brebo\Office\Intake\WebsiteOfficeLoginController;
use Brebo\Office\Intake\WebsiteOfficeLoginCsrf;
use Brebo\Office\Intake\WebsiteOfficeLoginFormRenderer;
use Brebo\Office\Intake\WebsiteOfficeLoginThrottle;
use Brebo\Office\Intake\WebsiteOfficeOperatorAuthenticator;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionIssuer;

require_once __DIR__ . '/../src/Intake/OfficeMount.php';
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
$csrf = $_COOKIE[OfficeMount::cookieName('login_csrf')] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  $csrf = WebsiteOfficeLoginCsrf::issue();
  setcookie(OfficeMount::cookieName('login_csrf'), $csrf, WebsiteOfficeLoginCsrf::cookieOptions($now + 900));
  header('Content-Type: text/html; charset=utf-8');
  echo (new WebsiteOfficeLoginFormRenderer())->render($csrf);
  exit;
}
// Require a same-origin browser POST; the reverse proxy must validate Host.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($origin) || !is_string($host)
  || !preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/iD', $host)
  || !hash_equals('https://' . $host, $origin)) {
  http_response_code(403);
  exit('Aanmelding geweigerd: ongeldige herkomst.');
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
  setcookie(OfficeMount::cookieName('login_csrf'), $newCsrf, WebsiteOfficeLoginCsrf::cookieOptions($now + 900));
  header('Content-Type: text/html; charset=utf-8');
  echo (new WebsiteOfficeLoginFormRenderer())->render($newCsrf, true);
  exit;
}
setcookie(OfficeMount::cookieName('login_csrf'), '', WebsiteOfficeLoginCsrf::cookieOptions($now - 3600));
setcookie(OfficeMount::cookieName('session'), $token, WebsiteOfficeLoginController::cookie($token, $now + 28800));
// Respect the active router namespace; the public /office/ mount uses short routes.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
// Successful login enters the shared Office home, not a single CRM module.
$queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
header('Location: ' . (OfficeMount::isTest() ? OfficeMount::route('home') : ($queryRouter ? '/index.php?office_route=home' : '/office/home')), true, 303);
