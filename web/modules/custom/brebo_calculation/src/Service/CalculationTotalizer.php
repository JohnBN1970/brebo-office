<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationRow;
use Drupal\brebo_calculation\Domain\CalculationTotals;
use Drupal\brebo_calculation\Domain\RuleType;

/** Central rule-aware financial totalizer. */
final class CalculationTotalizer {

  /** @param iterable<CalculationRow> $rows */
  public function total(iterable $rows): CalculationTotals {
    $base = 0.0;
    $allowances = 0.0;
    $adjustable = 0.0;
    $distributed = 0.0;
    $options = 0.0;

    foreach ($rows as $row) {
      $amount = $row->directCost();

      match ($row->type) {
        RuleType::Normal => $base += $amount,
        RuleType::Allowance => $allowances += $amount,
        RuleType::Adjustable => $adjustable += $amount,
        RuleType::Distributed => $distributed += $amount,
        RuleType::Option => $options += $amount,
        RuleType::Note => NULL,
      };
    }

    return new CalculationTotals(
      base: $base,
      allowances: $allowances,
      adjustable: $adjustable,
      distributed: $distributed,
      options: $options,
    );
  }

}
