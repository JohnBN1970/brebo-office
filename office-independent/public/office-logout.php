<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeCrmSessionIssuer;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'");

if (getenv('OFFICE_LOGIN_ENABLED') !== 'enabled' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503);
  exit('Office-afmelding is nog niet beschikbaar.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit('Gebruik POST om af te melden.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($origin) || !is_string($host) || !preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/iD', $host) || !hash_equals('https://' . $host, $origin)) {
  http_response_code(403);
  exit('Afmelding geweigerd: ongeldige herkomst.');
}
$token = $_COOKIE['office_session'] ?? null;
if (is_string($token) && strlen($token) >= 32 && strlen($token) <= 256) {
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!$dsn) {
    http_response_code(503);
    exit('Afmelden is tijdelijk niet beschikbaar.');
  }
  try {
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    (new WebsiteOfficeCrmSessionIssuer($db))->revoke($token);
  }
  catch (Throwable $exception) {
    error_log('Office logout unavailable: ' . get_class($exception));
    http_response_code(503);
    exit('Afmelden is tijdelijk niet beschikbaar.');
  }
}
setcookie('office_session', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
// Match the login route to the namespace used by the active public router.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$loginDestination = is_string($path) && str_starts_with($path, '/office/')
  ? '/office/inloggen'
  : '/inloggen';
header('Location: ' . $loginDestination, true, 303);
