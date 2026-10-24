<?php
declare(strict_types=1);

/**
 * Read-only Office-test router preflight.
 * Usage: php office-test-router-preflight.php /absolute/path/to/public_html/office-test/index.php
 * Never prints configuration secrets.
 */
$router = $argv[1] ?? '';
if (!is_string($router) || !is_file($router)) {
  fwrite(STDERR, "ROUTER: ONTBREEKT\n");
  exit(1);
}
$source = file_get_contents($router);
if (!is_string($source)) {
  fwrite(STDERR, "ROUTER: ONLEESBAAR\n");
  exit(1);
}
$checks = [
  'TESTCONFIG' => str_contains($source, '/office_calc_test/config/config.php'),
  'CONFIG_DOORGIFTE' => str_contains($source, 'putenv('),
  'PRIVATE_CONTROLLERS' => str_contains($source, "/public/"),
  'CRM_KANSEN' => str_contains($source, '/office-test/crm/kansen'),
  'PROJECTEN' => str_contains($source, '/office-test/projecten'),
  'ONSITE_ACTIVATIE' => str_contains($source, '/office-test/api/onsite/v1/activate'),
  'ONSITE_BOOTSTRAP' => str_contains($source, '/office-test/api/onsite/v1/bootstrap'),
  'ONSITE_AANWEZIGHEID' => str_contains($source, '/office-test/api/onsite/v1/presence'),
];
$failed = false;
foreach ($checks as $name => $passed) {
  echo $name . ': ' . ($passed ? 'GROEN' : 'ROOD') . PHP_EOL;
  $failed = $failed || !$passed;
}
// Optional second router: compare literal route maps without executing either router.
if (isset($argv[2])) {
  $candidate = $argv[2];
  if (!is_file($candidate) || !is_readable($candidate)) {
    fwrite(STDERR, "KANDIDAAT: ONLEESBAAR\\n");
    exit(1);
  }
  $candidateSource = file_get_contents($candidate);
  if (!is_string($candidateSource)) {
    fwrite(STDERR, "KANDIDAAT: ONLEESBAAR\\n");
    exit(1);
  }
  $extract = static function (string $php): array {
    preg_match_all("~['\\\"](/office-test(?:/[a-zA-Z0-9/_-]*)?)['\\\"]\\s*=>\\s*['\\\"]([a-zA-Z0-9-]+\\.php)['\\\"]~", $php, $matches, PREG_SET_ORDER);
    $routes = [];
    foreach ($matches as $match) {
      $routes[$match[1]] = $match[2];
    }
    ksort($routes);
    return $routes;
  };
  $activeRoutes = $extract($source);
  $candidateRoutes = $extract($candidateSource);
  foreach ($activeRoutes as $route => $controller) {
    if (($candidateRoutes[$route] ?? null) !== $controller) {
      echo 'ROUTEVERSCHIL: ' . $route . ' (' . $controller . ')' . PHP_EOL;
      $failed = true;
    }
  }
  echo 'ROUTEVERGELIJKING: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
  echo 'ACTIEVE ROUTES: ' . count($activeRoutes) . PHP_EOL;
  echo 'KANDIDAAT ROUTES: ' . count($candidateRoutes) . PHP_EOL;
}
exit($failed ? 1 : 0);
