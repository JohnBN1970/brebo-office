<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/deployment/hostinger';
$router = file_get_contents($root . '/index.php');
$rules = file_get_contents($root . '/.htaccess');
if ($router === false || $rules === false) {
  throw new RuntimeException('Hostinger deployment entrypoint missing.');
}
foreach ([
  '/office/inloggen' => 'office-login.php',
  '/office/uitloggen' => 'office-logout.php',
  '/office/crm/aanvragen' => 'crm-new-requests.php',
  '/office/crm/aanvragen/detail' => 'crm-new-requests.php',
] as $route => $target) {
  if (!str_contains($router, "'" . $route . "' => '" . $target . "'")) {
    throw new RuntimeException('Missing approved Office route: ' . $route);
  }
}
foreach (['calc-project-context.php', 'calculation-context$#D', 'OFFICE_PRIVATE_ROOT', 'realpath(', 'http_response_code(404)', 'http_response_code(503)'] as $required) {
  if (!str_contains($router, $required)) {
    throw new RuntimeException('Router safety requirement missing: ' . $required);
  }
}
foreach (['^api/workbench/v1/projects/[1-9][0-9]*/calculation-context, 'DirectoryIndex index.php', 'Require all denied', 'RewriteRule ^office/inloggen/', 'RewriteRule ^office/uitloggen/', 'RewriteRule ^office/crm/aanvragen/'] as $required) {
  if (!str_contains($rules, $required)) {
    throw new RuntimeException('Hostinger rewrite protection missing: ' . $required);
  }
}
if (str_contains($router, 'eval(') || str_contains($router, '$_GET[')) {
  throw new RuntimeException('Dynamic router execution or user-selected file paths are forbidden.');
}
echo "Hostinger Office release routing contract passed.\n";
, 'RewriteEngine On', 'DirectoryIndex index.php', 'Require all denied', 'RewriteRule ^office/inloggen/', 'RewriteRule ^office/uitloggen/', 'RewriteRule ^office/crm/aanvragen/'] as $required) {
  if (!str_contains($rules, $required)) {
    throw new RuntimeException('Hostinger rewrite protection missing: ' . $required);
  }
}
if (str_contains($router, 'eval(') || str_contains($router, '$_GET[')) {
  throw new RuntimeException('Dynamic router execution or user-selected file paths are forbidden.');
}
echo "Hostinger Office release routing contract passed.\n";
