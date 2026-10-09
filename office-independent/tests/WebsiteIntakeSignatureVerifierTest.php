<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeSignatureVerifier;

require_once __DIR__ . '/../src/Intake/WebsiteIntakeSignatureVerifier.php';

$secret = 'test-only-secret';
$verifier = new WebsiteIntakeSignatureVerifier($secret);
$id = 'aaaaaaaa-1111-4111-8111-111111111111';
$path = '/brebo-internal/intake/europakozijn';
$body = json_encode(['request_id' => $id], JSON_THROW_ON_ERROR);
$now = 1791500000;
$timestamp = (string) $now;
$canonical = implode("\n", ['POST', $path, hash('sha256', $body), $timestamp, $id]);
$headers = [
  'X-BREBO-Timestamp' => $timestamp,
  'X-BREBO-Request-Id' => $id,
  'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $canonical, $secret),
];
$verifier->verify('POST', $path, $body, $headers, $now);
foreach ([
  ['POST', $path, $body . ' ', $headers, $now],
  ['POST', '/wrong-path', $body, $headers, $now],
  ['POST', $path, $body, $headers, $now + 301],
  ['POST', $path, $body, array_replace($headers, ['X-BREBO-Request-Id' => 'bbbbbbbb-1111-4111-8111-111111111111']), $now],
] as $case) {
  try {
    $verifier->verify(...$case);
    throw new LogicException('Tampered or expired signed intake accepted.');
  }
  catch (RuntimeException) {
    // Rejected as expected.
  }
}
echo "Signed Worker intake checks passed.\n";
