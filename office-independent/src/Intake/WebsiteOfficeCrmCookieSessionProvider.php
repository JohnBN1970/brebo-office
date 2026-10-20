<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

require_once __DIR__ . '/OfficeMount.php';

/**
 * Bridges an Office-owned, HttpOnly Secure session cookie to the independent
 * CRM access policy. Never creates or authenticates sessions itself.
 */
final class WebsiteOfficeCrmCookieSessionProvider implements WebsiteCrmOperatorSessionProviderInterface {

  public function __construct(
    private readonly WebsiteOfficeCrmSessionRepository $sessions,
    private readonly array $cookies,
    private readonly int $now,
  ) {}

  public function currentOperator(): ?WebsiteCrmOperatorContext {
    $token = $this->cookies[OfficeMount::cookieName('session')] ?? null;
    if (!is_string($token)) {
      return null;
    }
    return $this->sessions->findActive($token, $this->now);
  }
}
