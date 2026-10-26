<?php

declare(strict_types=1);

// Fail-closed source contract for the test-only Hostinger entrypoint.
// This is not a substitute for an authenticated Hostinger browser smoke test.
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$htaccess = file_get_contents($root . '/deployment/hostinger/office-test.htaccess');
if (!is_string($router) || !is_string($htaccess)) {
  throw new RuntimeException('Test router artifacts missing.');
}
foreach ([
  '/office_calc_test/config/config.php',
  '/office_calc_test/release-next/private',
  "getenv('OFFICE_BASE_PATH') !== '/office-test'",
  "getenv('OFFICE_PRIVATE_ROOT') !== \$root",
  'u213420663_office_test',
  "getenv('OFFICE_PUBLIC_HOST')",
  "getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'",
  "getenv('OFFICE_CALC_LAUNCH_ENABLED') === 'enabled'",
] as $required) {
  if (!str_contains($router, $required)) {
    throw new RuntimeException('Test router safety contract missing: ' . $required);
  }
}
if (str_contains($router, '/office_private/config.php') || str_contains($router, 'u213420663_office_prod')) {
  throw new RuntimeException('Production dependency in test router.');
}
if (!str_contains($htaccess, 'Require all denied')) {
  throw new RuntimeException('Test mount must remain closed until access policy is installed.');
}
foreach (['/office-test/inloggen', '/office-test/uitloggen', '/office-test/'] as $route) {
  if (!str_contains($router, $route)) {
    throw new RuntimeException('Required test route missing: ' . $route);
  }
}
echo "Office test router source contract passed.\n";
