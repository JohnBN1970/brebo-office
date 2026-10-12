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
  || str_starts_with($documentRoot . '/', $private . '/')) {
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
];
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit('Pagina niet gevonden.');
}

require $private . '/public/' . $routes[$path];
