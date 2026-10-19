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
echo "OnSite iOS API routing static contract OK\n";
