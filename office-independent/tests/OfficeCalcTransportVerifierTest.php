<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcTransportVerifier.php';

use Brebo\Office\Calculation\OfficeCalcTransportVerifier;

$secret = str_repeat('k', 32);
$path = '/api/workbench/v1/projects/42/calculation-context';
$timestamp = '1791890000';
$requestId = '12345678-1234-4234-8234-123456789abc';
$canonical = implode("\n", ['GET', $path, hash('sha256', ''), $timestamp, $requestId]);
$headers = [
  'X-BREBO-Timestamp' => $timestamp,
  'X-BREBO-Request-Id' => $requestId,
  'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $canonical, $secret),
];
$verify = static fn (string $method, string $url, array $h, string $key, int $now): bool =>
  OfficeCalcTransportVerifier::verify($method, $url, '', $h, $key, $now);
if (!$verify('GET', $path, $headers, $secret, 1791890000)) {
  throw new RuntimeException('Valid Calc request rejected.');
}
foreach ([
  ['POST', $path, $headers, $secret, 1791890000],
  ['GET', '/wrong', $headers, $secret, 1791890000],
  ['GET', $path, $headers, $secret, 1791890300],
  ['GET', $path, $headers, 'invalid', 1791890000],
  ['GET', $path, array_merge($headers, ['X-BREBO-Signature' => 'v1=' . str_repeat('0', 64)]), $secret, 1791890000],
] as [$method, $url, $h, $key, $now]) {
  if ($verify($method, $url, $h, $key, $now)) {
    throw new RuntimeException('Invalid Calc request accepted.');
  }
}
echo "Office Calc transport signature checks passed.\n";
