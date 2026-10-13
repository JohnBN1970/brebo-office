<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Authenticates a Calc server request before any project context is read.
 * Callers MUST NOT run this for browser-supplied credentials.
 */
final class OfficeCalcAuthenticatedContextGate {
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
    if (strtoupper($method) !== 'GET'
      || !preg_match('#^/api/workbench/v1/projects/[1-9][0-9]*/calculation-context$#D', $path)) {
      return false;
    }
    if (!OfficeCalcTransportVerifier::verify($method, $path, $body, $headers, $this->sharedSecret, $now)) {
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
