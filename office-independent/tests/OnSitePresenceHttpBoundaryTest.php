<?php
declare(strict_types=1);
$entry = __DIR__ . '/../public/onsite-presence.php';
$source = file_get_contents($entry);
if ($source === false) throw new RuntimeException('OnSite boundary missing');
foreach ([
  "OFFICE_ONSITE_PRESENCE_ENABLED",
  "onsite_integration_incomplete",
  "onsite_not_enabled",
  "REQUEST_METHOD",
] as $required) {
  if (!str_contains($source, $required)) {
    throw new RuntimeException('Missing fail-closed boundary: ' . $required);
  }
}
$router = file_get_contents(__DIR__ . '/../deployment/hostinger/index.php');
if ($router === false || !str_contains($router, "'/api/onsite/v1/presence'") ||
    !str_contains($router, "'/public/onsite-presence.php'")) {
  throw new RuntimeException('OnSite presence route missing');
}
$rewrite = file_get_contents(__DIR__ . '/../deployment/hostinger/.htaccess');
if ($rewrite === false || !str_contains($rewrite, 'api/onsite/v1/presence')) {
  throw new RuntimeException('Hostinger Apache presence rewrite missing');
}
$testRouter = file_get_contents(__DIR__ . '/../deployment/hostinger/office-test-index.php');
if ($testRouter === false || !str_contains($testRouter, "'/office-test/api/onsite/v1/presence'")) {
  throw new RuntimeException('Isolated Office-test presence route missing');
}
echo "OnSitePresenceHttpBoundaryTest OK\n";
