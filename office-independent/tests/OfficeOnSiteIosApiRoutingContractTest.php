<?php
declare(strict_types=1);
$client = file_get_contents(dirname(__DIR__, 2) . '/ios/CrewPOC/Sources/OnSiteAPIClient.swift');
if (!is_string($client)) throw new RuntimeException('iOS client missing');
foreach ([
  '/api/onsite/v1/activate',
  '/api/onsite/v1/bootstrap',
  '/api/onsite/v1/presence',
] as $route) {
  if (!str_contains($client, $route)) throw new RuntimeException('Missing iOS route: ' . $route);
}
if (substr_count($client, 'let url = endpointURL(path)') !== 3) {
  throw new RuntimeException('All GET and POST request methods must preserve the configured base path');
}
if (str_contains($client, 'baseURL.appending(path: path)')) {
  throw new RuntimeException('Unscoped API URL bypasses configured base path');
}
if (!str_contains($client, 'OnSiteAPIBaseURL')
  || !str_contains($client, 'prefix + "/" + suffix')) {
  throw new RuntimeException('Configured Office test prefix not preserved');
}
$router = file_get_contents(dirname(__DIR__) . '/deployment/hostinger/office-test-index.php');
if (!is_string($router)) throw new RuntimeException('Office test router missing');
foreach (['activate', 'bootstrap', 'presence'] as $route) {
  if (!str_contains($router, '/office-test/api/onsite/v1/' . $route)) {
    throw new RuntimeException('Office test router missing app endpoint: ' . $route);
  }
}
// SMS link endpoints remain present in the client but are not routed in Office test.
// Keep this discrepancy visible until onboarding is explicitly migrated.
foreach (['link/request', 'link/verify'] as $route) {
  if (str_contains($router, '/office-test/api/onsite/v1/' . $route)) {
    throw new RuntimeException('SMS onboarding unexpectedly enabled on Office test');
  }
}
echo "OnSite iOS API routing static contract OK; SMS onboarding not exposed in Office test\\n";
