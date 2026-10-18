<?php

declare(strict_types=1);

/**
 * Isolated Hostinger test-only entrypoint. Install as public_html/office-test/index.php.
 * Never reuse the production Office router or its configuration file.
 * Requires a separate HTTP authentication gate before browser exposure.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$configFile = '/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';
if (!is_file($configFile)) {
  http_response_code(503);
  exit('Testconfiguratie ontbreekt.');
}
$config = require $configFile;
if (!is_array($config)) {
  http_response_code(503);
  exit('Ongeldige testconfiguratie.');
}
foreach ($config as $key => $value) {
  if (!is_string($key) || !is_string($value)) {
    http_response_code(503);
    exit('Ongeldige testinstelling.');
  }
  putenv($key . '=' . $value);
}
$root = '/home/u213420663/domains/brebobv.nl/office_calc_test/release-next/private';
$private = realpath($root);
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_PRIVATE_ROOT') !== $root
  || $private === false || !is_dir($private . '/public')
  || !is_dir($private . '/src')
  || !is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
  || getenv('OFFICE_INTAKE_DB_USER') !== 'u213420663_office_test'
  || !is_string($allowedHost) || $allowedHost === ''
  || !is_string($host) || !hash_equals($allowedHost, $host)
  || in_array(strtolower($host), ['office.brebobv.nl', 'www.office.brebobv.nl'], true)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
  || (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled' && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled')
  || getenv('OFFICE_CALC_LAUNCH_ENABLED') === 'enabled') {
  http_response_code(503);
  exit('Testomgeving niet beschikbaar.');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$routes = [
  '/office-test' => 'office-home.php',
  '/office-test/' => 'office-home.php',
  '/office-test/inloggen' => 'office-login.php',
  '/office-test/uitloggen' => 'office-logout.php',
  '/office-test/crm/aanvragen' => 'crm-new-requests.php',
  '/office-test/crm/aanvragen/detail' => 'crm-new-requests.php',
  '/office-test/projecten' => 'office-projects.php',
  '/office-test/projecten/detail' => 'office-project.php',
];
if ($path === '/office-test/inzet/apparaten') {
  require $private . '/public/onsite-devices.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/activate') {
  require $private . '/public/onsite-activate.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/bootstrap') {
  require $private . '/public/onsite-bootstrap.php';
  exit;
}
if ($path === '/office-test/api/onsite/v1/presence') {
  // Isolated test environment: no production token or assignment fallback.
  require $private . '/public/onsite-presence.php';
  exit;
}
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit('Testpagina niet gevonden.');
}
require $private . '/public/' . $routes[$path];
