<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Server-to-server authentication gate for Calc grant checks.
 * The caller must use POST with a signed JSON body and a unique request ID.
 * Browser credentials are never accepted as authorization.
 */
final class OfficeCalcAuthenticatedGrantGate {

  public const PATH = '/api/workbench/v1/calculation-grants/validate';

  public function __construct(
    private readonly OfficeCalcRequestReplayGuard $replays,
    private readonly string $sharedSecret,
  ) {}

  public function allow(
    string $method,
    string $path,
    string $body,
    array $headers,
    ?int $now = null,
  ): bool {
    if (strtoupper($method) !== 'POST' || $path !== self::PATH
      || strlen($body) < 2 || strlen($body) > 4096) {
      return false;
    }
    if (!OfficeCalcTransportVerifier::verify(
      'POST', $path, $body, $headers, $this->sharedSecret, $now
    )) {
      return false;
    }
    $normalized = [];
    foreach ($headers as $name => $value) {
      if (is_string($name) && is_string($value)) {
        $normalized[strtolower($name)] = $value;
      }
    }
    $timestamp = $normalized['x-brebo-timestamp'] ?? '';
    $requestId = $normalized['x-brebo-request-id'] ?? '';
    return $this->replays->accept($requestId, (int) $timestamp, $now);
  }
}
