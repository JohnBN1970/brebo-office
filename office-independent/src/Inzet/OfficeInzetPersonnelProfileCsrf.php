<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Personnel-profile CSRF is purpose-separated from shift writes.
 * Session ID must come from the verified Office session provider.
 */
final class OfficeInzetPersonnelProfileCsrf {
  public static function token(string $sessionId, string $secret): string {
    if (preg_match('/^[a-f0-9]{64}$/D', $sessionId) !== 1 || strlen($secret) < 32) {
      throw new \InvalidArgumentException('Verified session and secret required');
    }
    return hash_hmac('sha256', 'office-inzet-personnel-profile:v1:' . $sessionId, $secret);
  }

  public static function verify(mixed $submitted, string $sessionId, string $secret): bool {
    if (!is_string($submitted) || preg_match('/^[a-f0-9]{64}$/D', $submitted) !== 1) return false;
    try {
      return hash_equals(self::token($sessionId, $secret), $submitted);
    } catch (\InvalidArgumentException $exception) {
      return false;
    }
  }
}
