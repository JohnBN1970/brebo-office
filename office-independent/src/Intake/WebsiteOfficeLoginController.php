<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Independent Office login flow. Mount only behind HTTPS with rate limiting.
 * CSRF tokens are tied to a separate, secure, HttpOnly pre-auth cookie.
 */
final class WebsiteOfficeLoginController {

  public function __construct(
    private readonly WebsiteOfficeOperatorAuthenticator $accounts,
    private readonly WebsiteOfficeCrmSessionIssuer $sessions,
  ) {}

  public function login(string $username, string $password, int $now): ?string {
    $account = $this->accounts->authenticate($username, $password);
    if ($account === null) {
      return null;
    }
    return $this->sessions->issue($account['operator_id'], $account['can_edit_crm'], $now);
  }

  public function logout(string $sessionToken): void {
    $this->sessions->revoke($sessionToken);
  }

  public static function cookie(string $token, int $expiresAt): array {
    return [
      'expires' => $expiresAt,
      'path' => '/office',
      'secure' => true,
      'httponly' => true,
      'samesite' => 'Strict',
    ];
  }
}
