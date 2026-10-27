<?php
declare(strict_types=1);

// Static contract test for the isolated Hostinger test router.
// Do not execute the router: it requires private config and a live environment.
$router = file_get_contents(dirname(__DIR__) . '/deployment/hostinger/office-test-index.php');
if (!is_string($router) || $router === '') {
  fwrite(STDERR, "Test router unavailable\n");
  exit(1);
}
$required = [
  "getenv('OFFICE_BASE_PATH') !== '/office-test'",
  "getenv('OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED') !== 'enabled'",
  "putenv('OFFICE_TEST_NAV_V2_ENABLED=disabled')",
  "putenv('OFFICE_TEST_WORKSPACE_V2_ENABLED=disabled')",
];
foreach ($required as $clause) {
  if (!str_contains($router, $clause)) {
    fwrite(STDERR, "Missing rollout gate clause: " . $clause . "\n");
    exit(1);
  }
}
$gate = strpos($router, "getenv('OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED')");
$routes = strpos($router, '$routes = [');
if ($gate === false || $routes === false || $gate > $routes) {
  fwrite(STDERR, "Rollout gate must precede route dispatch\n");
  exit(1);
}
echo "OfficeTestUiRolloutGateTest OK\n";
