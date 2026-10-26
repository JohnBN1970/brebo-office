<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Authorization boundary for independent Office CRM screens.
 * Authentication is supplied by a verified server-side Office session adapter.
 */
final class WebsiteOfficeCrmAccessPolicy {

  public function __construct(private readonly WebsiteCrmOperatorSessionProviderInterface $sessions) {}

  public function requireReader(): WebsiteCrmOperatorContext {
    $operator = $this->sessions->currentOperator();
    if ($operator === null) {
      throw new \DomainException('Authenticated Office session required.');
    }
    // Current operator context only contains edit permission. Until a distinct
    // CRM read permission is modeled, do not silently grant every user access.
    $operator->requireCrmEdit();
    return $operator;
  }
}
