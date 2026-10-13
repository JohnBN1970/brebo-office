<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;

/** Reuse the existing verified Office session; never accept browser-supplied roles. */
final class OfficeCalculationSessionAccess {
  public function __construct(
    private readonly WebsiteCrmOperatorSessionProviderInterface $sessions,
    private readonly PdoOfficeCalculationPermissionRepository $permissions,
  ) {}

  public function requireCalculator(): WebsiteCrmOperatorContext {
    $operator = $this->sessions->currentOperator();
    if ($operator === null) {
      throw new \DomainException('Calculation access denied.');
    }
    return OfficeCalculationPermission::requireCalculator(
      $operator,
      $this->permissions->canCalculate($operator->operatorId),
    );
  }
}
