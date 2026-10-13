<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;

/**
 * Explicit calculator permission, independent from CRM edit permission.
 * The permission must be supplied by trusted server-side Office storage.
 */
final class OfficeCalculationPermission {
  public static function requireCalculator(
    ?WebsiteCrmOperatorContext $operator,
    bool $canCalculate,
  ): WebsiteCrmOperatorContext {
    if ($operator === null || !$canCalculate) {
      throw new \DomainException('Calculation access denied.');
    }
    return $operator;
  }
}
