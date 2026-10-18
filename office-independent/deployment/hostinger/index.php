<?php

declare(strict_types=1);

/**
 * Hostinger Business public entrypoint.
 * Deploy ONLY this file to public_html/office/index.php.
 * Keep office-independent/public and src in a private directory outside every webroot.
 * Set OFFICE_PRIVATE_ROOT to that directory's absolute path on the server.
 */
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

// Match the already deployed Hostinger bootstrap: configuration stays private.
$configFile = '/home/u213420663/domains/brebobv.nl/office_private/config.php';
if (!is_file($configFile)) {
  http_response_code(503);
  exit('Office-configuratie ontbreekt.');
}
$config = require $configFile;
if (!is_array($config)) {
  http_response_code(503);
  exit('Ongeldige Office-configuratie.');
}
foreach ($config as $key => $value) {
  if (!is_string($key) || !is_string($value)) {
    http_response_code(503);
    exit('Ongeldige Office-instelling.');
  }
  putenv($key . '=' . $value);
}
$root = getenv('OFFICE_PRIVATE_ROOT');
$private = is_string($root) && $root !== '' ? realpath($root) : false;
$documentRoot = realpath(__DIR__);
if ($private === false || $documentRoot === false
  || !is_dir($private . '/public')
  || str_starts_with($private . '/', $documentRoot . '/')
  || $private === $documentRoot
  || str_starts_with($documentRoot . '/', $private . '/')
  || str_contains($private, '/public_html/')
  || str_ends_with($private, '/public_html')) {
  http_response_code(503);
  exit('Office is nog niet beschikbaar.');
}

// This public entrypoint exposes only explicitly reviewed routes.
// Never route arbitrary filenames or accept a caller-controlled PHP path.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
// Legacy Hostinger installation uses index.php?office_route=... rather than rewrites.
// Allow only exact route names; never use the parameter as a file path.
$requestedRoute = filter_input(INPUT_GET, 'office_route', FILTER_UNSAFE_RAW);
if ($path === '/index.php' && $requestedRoute === null) {
  $path = '/inloggen';
}
if ($path === '/index.php' && $requestedRoute !== null) {
  $legacyRoutes = [
    'home' => '/home',
    'inloggen' => '/inloggen',
    'uitloggen' => '/uitloggen',
    'crm/aanvragen' => '/crm/aanvragen',
    'crm/aanvragen/detail' => '/crm/aanvragen/detail',
    'calc-werkbank' => '/calc-werkbank',
    'projecten/detail' => '/projecten/detail',
  ];
  $requested = $requestedRoute;
  if (is_string($requested) && isset($legacyRoutes[$requested])) {
    $path = $legacyRoutes[$requested];
  }
}
$routes = [
  '/home' => 'office-home.php',
  '/office' => 'office-home.php',
  '/office/' => 'office-home.php',
  '/office/home' => 'office-home.php',
  '/office/inloggen' => 'office-login.php',
  '/office/uitloggen' => 'office-logout.php',
  '/office/crm/aanvragen' => 'crm-new-requests.php',
  '/office/crm/aanvragen/detail' => 'crm-new-requests.php',
  // Existing live /office/ entrypoint strips the /office prefix before routing.
  // Preserve these deployed route names when the independent router is adopted.
  '/inloggen' => 'office-login.php',
  '/uitloggen' => 'office-logout.php',
  '/crm/aanvragen' => 'crm-new-requests.php',
  '/crm/aanvragen/detail' => 'crm-new-requests.php',
];
if ($path === '/office-test/inzet/dienst/wijzigen') {
  require $private . '/public/office-inzet-shift-reschedule.php';
  exit;
}
if ($path === '/office-test/inzet/dienst/annuleren') {
  require $private . '/public/office-inzet-shift-cancel.php';
  exit;
}
if ($path === '/office-test/inzet/dienst/nieuw') {
  // Explicit test-only write route. Endpoint independently checks all write gates.
  require $private . '/public/office-inzet-shift-create.php';
  exit;
}
if ($path === '/office/calc-werkbank' || $path === '/calc-werkbank') {
  $_SERVER['OFFICE_QUERY_ROUTER'] = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php' ? '1' : '0';
  $_SERVER['REQUEST_URI'] = '/calc-werkbank';
  // Office-owned entry gate; authentication and permissions are checked by the endpoint.
  // This does not proxy or expose Calc itself.
  require $private . '/public/calc-workbench.php';
  exit;
}
if ($path === '/office/projecten/detail' || $path === '/projecten/detail') {
  require $private . '/public/office-project.php';
  exit;
}
if ($path === '/office/inzet/apparaten' || $path === '/inzet/apparaten') {
  require $private . '/public/onsite-devices.php';
  exit;
}
if ($path === '/api/onsite/v1/activate' || $path === '/office/api/onsite/v1/activate') {
  require $private . '/public/onsite-activate.php';
  exit;
}
if ($path === '/api/onsite/v1/bootstrap' || $path === '/office/api/onsite/v1/bootstrap') {
  require $private . '/public/onsite-bootstrap.php';
  exit;
}
if ($path === '/api/onsite/v1/presence' || $path === '/office/api/onsite/v1/presence') {
  // Office-native OnSite API; endpoint stays disabled until identity and
  // project/zone assignment integration are explicitly configured.
  require $private . '/public/onsite-presence.php';
  exit;
}
if ($path === '/calc/start') {
  // Same-origin Office session and permissions are checked by this disabled-by-default endpoint.
  require $private . '/public/calc-start.php';
  exit;
}
if (is_string($path) && preg_match('#^/api/workbench/v1/projects/[1-9][0-9]*/calculation-context$#D', $path)) {
  // Signed server-to-server endpoint, independently disabled by default.
  require $private . '/public/calc-project-context.php';
  exit;
}
if ($path === '/api/workbench/v1/calculation-grants/validate') {
  // HMAC-signed server-to-server validation, disabled unless explicitly enabled.
  require $private . '/public/calc-grant-validation.php';
  exit;
}
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit('Pagina niet gevonden.');
}

// CRM handler validates the canonical Office path itself. Normalize only
// approved CRM routes, without altering query parameters (id/page).
if ($path === '/crm/aanvragen' || $path === '/crm/aanvragen/detail') {
  $_SERVER['REQUEST_URI'] = '/office' . $path;
}
require $private . '/public/' . $routes[$path];
