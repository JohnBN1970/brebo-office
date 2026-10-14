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
$routes = [
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
if ($path === '/calc-werkbank') {
  // Office-owned entry gate; authentication and permissions are checked by the endpoint.
  // This does not proxy or expose Calc itself.
  require $private . '/public/calc-workbench.php';
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
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit('Pagina niet gevonden.');
}

require $private . '/public/' . $routes[$path];
