<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use RuntimeException;

/** Verifies the existing Cloudflare Worker HMAC v1 handoff. */
final class WebsiteIntakeSignatureVerifier {

  public function __construct(private readonly string $secret, private readonly int $maxAgeSeconds = 300) {
    if ($secret === '') {
      throw new RuntimeException('Intake signing secret is not configured.');
    }
  }

  public function verify(string $method, string $path, string $body, array $headers, int $now): void {
    $headers = array_change_key_case($headers, CASE_LOWER);
    $timestamp = $headers['x-brebo-timestamp'] ?? '';
    $requestId = $headers['x-brebo-request-id'] ?? '';
    $signature = $headers['x-brebo-signature'] ?? '';
    if (!is_string($timestamp) || !preg_match('/^[0-9]{10,12}$/', $timestamp)
      || abs($now - (int) $timestamp) > $this->maxAgeSeconds
      || !is_string($requestId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId)
      || !is_string($signature) || !preg_match('/^v1=[0-9a-f]{64}$/i', $signature)) {
      throw new RuntimeException('Invalid or expired intake signature headers.');
    }
    $canonical = implode("\n", [strtoupper($method), $path, hash('sha256', $body), $timestamp, $requestId]);
    $expected = 'v1=' . hash_hmac('sha256', $canonical, $this->secret);
    if (!hash_equals($expected, strtolower($signature))) {
      throw new RuntimeException('Intake signature mismatch.');
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['request_id'] ?? null) !== $requestId) {
      throw new RuntimeException('Signed request ID does not match payload.');
    }
  }
}
