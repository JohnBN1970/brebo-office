<?php
declare(strict_types=1);
/**
 * Read-only candidate router deployment preflight.
 * Usage: php office-test-candidate-release-preflight.php CANDIDATE_ROUTER PRIVATE_RELEASE
 * Does not load the router, config or module manifests and never connects to DB.
 */
$router = $argv[1] ?? '';
$private = $argv[2] ?? '';
if (!is_string($router) || !is_file($router) || !is_readable($router)
  || !is_string($private) || !is_dir($private)) {
  fwrite(STDERR, "INPUT: ROOD\n");
  exit(1);
}
$source = file_get_contents($router);
if (!is_string($source)) exit(1);
$failed = false;
$check = static function (string $name, bool $ok) use (&$failed): void {
  echo $name . ': ' . ($ok ? 'GROEN' : 'ROOD') . PHP_EOL;
  $failed = $failed || !$ok;
};
$check('TEST_CONFIG', str_contains($source, '/office_calc_test/config/config.php'));
$check('TEST_DB_GATE', str_contains($source, 'u213420663_office_test'));
$check('PRIVATE_RELEASE_GATE', str_contains($source, 'OFFICE_PRIVATE_ROOT'));
$check('HTTPS_GATE', str_contains($source, "HTTPS"));
$check('HOST_GATE', str_contains($source, 'OFFICE_PUBLIC_HOST'));
$check('MODULE_DUPLICATE_GATE', str_contains($source, 'isset($routes[$route])'));
$check('MODULE_RESERVED_GATE', str_contains($source, 'in_array($route, $reservedRoutes, true)'));
$check('UNKNOWN_ROUTE_404', str_contains($source, 'http_response_code(404)'));
preg_match_all("~['\"](/office-test(?:/[a-zA-Z0-9/_-]*)?)['\"]\\s*=>\\s*['\"]([a-zA-Z0-9-]+\\.php)['\"]~", $source, $matches, PREG_SET_ORDER);
$routes = [];
foreach ($matches as $match) $routes[$match[1]] = $match[2];
foreach ([
  '/office-test/inzet/apparaten' => 'onsite-devices.php',
  '/office-test/api/onsite/v1/activate' => 'onsite-activate.php',
  '/office-test/api/onsite/v1/bootstrap' => 'onsite-bootstrap.php',
  '/office-test/api/onsite/v1/presence' => 'onsite-presence.php',
] as $route => $controller) {
  if (str_contains($source, '$path === ' . var_export($route, true))
    && str_contains($source, chr(39) . '/public/' . $controller . chr(39))) {
    $routes[$route] = $controller;
  } else {
    $check('DEDICATED_ROUTE ' . $route, false);
  }
}
ksort($routes);
foreach ($routes as $route => $controller) {
  $file = $private . '/public/' . $controller;
  $check('CONTROLLER ' . $route, is_file($file) && is_readable($file));
}
$moduleRoot = $private . '/modules';
if (is_dir($moduleRoot)) {
  foreach (glob($moduleRoot . '/*/routes.php') ?: [] as $manifest) {
    // Do not execute PHP manifests during preflight.
    $check('MODULE_MANIFEST ' . basename(dirname($manifest)), is_file($manifest) && is_readable($manifest));
  }
}
echo 'CANDIDATE_RELEASE_PREFLIGHT: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
exit($failed ? 1 : 0);
