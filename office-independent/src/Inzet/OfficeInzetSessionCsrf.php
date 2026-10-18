<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Deterministic CSRF token bound to a verified Office session hash.
 * The secret is server-side configuration, never delivered to the browser.
 * A revoked or replaced Office session cannot reuse its previous token.
 */
final class OfficeInzetSessionCsrf {
  public static function token(string $sessionId, string $secret): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $sessionId) || strlen($secret) < 32) {
      throw new \InvalidArgumentException('Verified Office session and server secret required.');
    }
    return hash_hmac('sha256', 'office-inzet-shift-write:v1:' . $sessionId, $secret);
  }

  public static function verify(mixed $submitted, string $sessionId, string $secret): bool {
    if (!is_string($submitted) || preg_match('/^[a-f0-9]{64}$/D', $submitted) !== 1) {
      return false;
    }
    try {
      return hash_equals(self::token($sessionId, $secret), $submitted);
    } catch (\InvalidArgumentException $e) {
      return false;
    }
  }
}
