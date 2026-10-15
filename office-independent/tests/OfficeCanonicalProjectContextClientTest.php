<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContextClient.php';

use Brebo\Office\Project\OfficeCanonicalProjectContextClient;

foreach (['http://example.com', 'https://example.com/path', 'https://user@example.com'] as $url) {
  try {
    new OfficeCanonicalProjectContextClient($url, str_repeat('s', 32));
    throw new RuntimeException('Insecure endpoint accepted.');
  } catch (RuntimeException $expected) {
    if ($expected->getMessage() === 'Insecure endpoint accepted.') throw $expected;
  }
}
$client = new OfficeCanonicalProjectContextClient('https://example.com', str_repeat('s', 32));
try {
  $client->get(0);
  throw new RuntimeException('Invalid project ID accepted.');
} catch (RuntimeException $expected) {
  if ($expected->getMessage() === 'Invalid project ID accepted.') throw $expected;
}
$path = '/api/workbench/v1/projects/42/calculation-context';
$timestamp = '1792080000';
$requestId = '12345678-1234-1234-1234-123456789abc';
$secret = str_repeat('s', 32);
$expected = 'v1=' . hash_hmac('sha256', "GET\n{$path}\n" . hash('sha256', '') . "\n{$timestamp}\n{$requestId}", $secret);
if (OfficeCanonicalProjectContextClient::signGet($path, $timestamp, $requestId, $secret) !== $expected) {
  throw new RuntimeException('Drupal HMAC contract mismatch.');
}
foreach (['/wrong/42', '/api/workbench/v1/projects/0/calculation-context'] as $invalidPath) {
  try {
    OfficeCanonicalProjectContextClient::signGet($invalidPath, $timestamp, $requestId, $secret);
    throw new RuntimeException('Invalid signed route accepted.');
  } catch (RuntimeException $expectedError) {
    if ($expectedError->getMessage() === 'Invalid signed route accepted.') throw $expectedError;
  }
}
echo "Canonical project context client guards passed.\n";
