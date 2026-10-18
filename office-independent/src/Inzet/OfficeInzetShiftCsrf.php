<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Session-bound anti-CSRF token for native Office shift writes.
 * The token must be kept server-side in the authenticated Office session,
 * never in a URL, browser-controlled cookie, or database project record.
 */
final class OfficeInzetShiftCsrf {
  public static function issue(): string {
    return bin2hex(random_bytes(32));
  }

  public static function verify(mixed $submitted, mixed $sessionToken): bool {
    return is_string($submitted)
      && is_string($sessionToken)
      && preg_match('/^[a-f0-9]{64}$/D', $submitted) === 1
      && preg_match('/^[a-f0-9]{64}$/D', $sessionToken) === 1
      && hash_equals($sessionToken, $submitted);
  }
}
