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
// Use the private release selected by the isolated test configuration.
// Never infer a release from the public request path.
$root = getenv('OFFICE_PRIVATE_ROOT');
if (!is_string($root) || !preg_match('~^/home/u213420663/domains/brebobv\\.nl/office_calc_test/release-[a-zA-Z0-9-]+/private$~D', $root)) {
  http_response_code(503);
  exit('Ongeldige testrelease.');
}
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
  '/office-test/crm/bedrijven' => 'crm-workspace.php',
  '/office-test/crm/contactpersonen' => 'crm-workspace.php',
  '/office-test/crm/kansen' => 'crm-workspace.php',
  '/office-test/crm/prospects' => 'crm-workspace.php',
  '/office-test/crm/aanvragen' => 'crm-new-requests.php',
  '/office-test/crm/aanvragen/detail' => 'crm-new-requests.php',
  '/office-test/projecten' => 'office-projects.php',
  '/office-test/inzet/dienst/nieuw' => 'office-inzet-shift-create.php',
  '/office-test/inzet/medewerker/personeelsnummer' => 'office-inzet-personnel-number.php',
  '/office-test/inzet/onsite/activatie-uitgeven' => 'office-onsite-invitation.php',
  '/office-test/inzet/onsite/apparaat-intrekken' => 'office-onsite-device-revoke.php',
  '/office-test/projecten/detail' => 'office-project.php',
  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',
  '/office-test/inzet/aanwezigheid' => 'office-onsite-presence-review.php',
  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',
  '/office-test/inzet/zone/bewerken' => 'office-onsite-zone-update.php',
];
// Trusted module route manifests are discovered from the deployed private release.
// Legacy routes remain authoritative; module routes cannot override them.
$moduleRoot = $private . '/modules';
if (is_dir($moduleRoot)) {
  $manifests = glob($moduleRoot . '/*/routes.php') ?: [];
  sort($manifests, SORT_STRING);
  foreach ($manifests as $manifest) {
    $moduleDir = realpath(dirname($manifest));
    $moduleBase = realpath($moduleRoot);
    if ($moduleDir === false || $moduleBase === false
      || dirname($moduleDir) !== $moduleBase || !is_file($manifest)) {
      http_response_code(503); exit('Ongeldige moduleconfiguratie.');
    }
    $moduleRoutes = require $manifest;
    if (!is_array($moduleRoutes)) {
      http_response_code(503); exit('Ongeldige moduleroutes.');
    }
    $reservedRoutes = [
      '/office-test/inzet/apparaten',
      '/office-test/api/onsite/v1/activate',
      '/office-test/api/onsite/v1/bootstrap',
      '/office-test/api/onsite/v1/presence',
    ];
    foreach ($moduleRoutes as $route => $controller) {
      if (!is_string($route) || !preg_match('~^/office-test/[a-z0-9/_-]+$~D', $route)
        || !is_string($controller)
        || !preg_match('/^[a-z][a-z0-9-]*\\.php$/D', $controller)
        || isset($routes[$route]) || in_array($route, $reservedRoutes, true)) {
        http_response_code(503); exit('Ongeldige of dubbele moduleroute.');
      }
      $target = realpath($private . '/public/' . $controller);
      if ($target === false || dirname($target) !== realpath($private . '/public')) {
        http_response_code(503); exit('Modulecontroller ontbreekt.');
      }
      $routes[$route] = $controller;
    }
  }
}
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
