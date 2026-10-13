<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/** Verify Calc's officeTransport.ts v1 server-to-server HMAC envelope. */
final class OfficeCalcTransportVerifier {
  public static function verify(
    string $method,
    string $path,
    string $body,
    array $headers,
    string $sharedSecret,
    ?int $now = null,
  ): bool {
    if (strlen($sharedSecret) < 32 || !str_starts_with($path, '/') || str_contains($path, "\n")) {
      return false;
    }
    $normalized = [];
    foreach ($headers as $key => $value) {
      if (is_string($key) && is_string($value)) {
        $normalized[strtolower($key)] = $value;
      }
    }
    $timestamp = $normalized['x-brebo-timestamp'] ?? '';
    $requestId = $normalized['x-brebo-request-id'] ?? '';
    $signature = $normalized['x-brebo-signature'] ?? '';
    if (!preg_match('/^[0-9]{10,11}$/D', $timestamp)
      || !preg_match('/^[a-f0-9-]{36}$/iD', $requestId)
      || !preg_match('/^v1=([a-f0-9]{64})$/D', $signature, $matches)) {
      return false;
    }
    if (abs(($now ?? time()) - (int) $timestamp) > 120) {
      return false;
    }
    $canonical = implode("\n", [
      strtoupper($method),
      $path,
      hash('sha256', $body),
      $timestamp,
      $requestId,
    ]);
    return hash_equals(hash_hmac('sha256', $canonical, $sharedSecret), $matches[1]);
  }
}
