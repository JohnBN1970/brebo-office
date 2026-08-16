<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\ReconciliationResult;

/** Makes migration differences explicit instead of silently accepting them. */
final class LegacyReconciler {

  public function compare(float $legacyAmount, float $newAmount, float $tolerance = 0.01): ReconciliationResult {
    if ($tolerance < 0) {
      throw new \InvalidArgumentException('Tolerance cannot be negative.');
    }

    $difference = $newAmount - $legacyAmount;

    return new ReconciliationResult(
      legacyAmount: $legacyAmount,
      newAmount: $newAmount,
      difference: $difference,
      tolerance: $tolerance,
      matches: abs($difference) <= $tolerance,
    );
  }

}
