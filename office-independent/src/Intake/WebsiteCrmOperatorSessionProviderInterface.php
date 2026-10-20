<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Implement only against Office's verified server-side login/session system.
 * Never derive the identity, session or permission from browser form data,
 * cookies without validation, or untrusted request headers.
 */
interface WebsiteCrmOperatorSessionProviderInterface {

  /**
   * Return null when no authenticated, active Office session exists.
   * Implementations must check session expiry, revocation and CRM permissions.
   */
  public function currentOperator(): ?WebsiteCrmOperatorContext;
}
