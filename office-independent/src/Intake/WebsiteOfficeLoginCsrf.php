<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Double-submit CSRF bound to a separate pre-auth cookie, not the Office session. */
final class WebsiteOfficeLoginCsrf {

  public static function issue(): string {
    return bin2hex(random_bytes(32));
  }

  public static function verify(mixed $submitted, mixed $cookie): bool {
    return is_string($submitted)
      && is_string($cookie)
      && preg_match('/^[a-f0-9]{64}$/D', $submitted) === 1
      && preg_match('/^[a-f0-9]{64}$/D', $cookie) === 1
      && hash_equals($cookie, $submitted);
  }

  public static function cookieOptions(int $expiresAt): array {
    return [
      'expires' => $expiresAt,
      'path' => '/office',
      'secure' => true,
      'httponly' => true,
      'samesite' => 'Strict',
    ];
  }
}
