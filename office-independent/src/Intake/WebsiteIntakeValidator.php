<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use InvalidArgumentException;

/**
 * Validates the independent website intake boundary before persistence.
 * This class has no Drupal dependencies.
 */
final class WebsiteIntakeValidator {

  /** @param array<string, mixed> $payload */
  public function validate(array $payload): void {
    $requestId = $payload['request_id'] ?? null;
    if (!is_string($requestId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId)) {
      throw new InvalidArgumentException('Invalid request_id.');
    }
    foreach (['source', 'schema_version'] as $key) {
      if (!is_string($payload[$key] ?? null) || trim($payload[$key]) === '') {
        throw new InvalidArgumentException('Missing or invalid ' . $key . '.');
      }
    }
    foreach (['observed', 'detected', 'calculated', 'selected'] as $key) {
      if (!is_array($payload[$key] ?? null)) {
        throw new InvalidArgumentException('Missing or invalid ' . $key . '.');
      }
    }
    $observed = $payload['observed'];
    if (!is_array($observed['building'] ?? null)
      || !is_array($observed['rooms'] ?? null) || $observed['rooms'] === []
      || !is_array($observed['frames'] ?? null) || $observed['frames'] === []) {
      throw new InvalidArgumentException('Incomplete observed building geometry.');
    }
  }
}
