<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationRow;
use Drupal\brebo_calculation\Domain\DistributionBasis;
use Drupal\brebo_calculation\Domain\DistributionRule;
use Drupal\brebo_calculation\Domain\RuleType;

/** Allocates a distributed row without changing its total source cost. */
final class DistributionCalculator {

  /**
   * @param array<string, float> $basisValues Values per target paragraph for
   *   direct_cost/labour/material/quantity. Ignored for equal/manual.
   * @return array<string, float> Allocated amount per target paragraph.
   */
  public function allocate(CalculationRow $source, DistributionRule $rule, array $basisValues = []): array {
    if ($source->legacyLineId !== $rule->sourceLineId) {
      throw new \InvalidArgumentException('Distribution rule does not belong to source row.');
    }
    if ($source->type !== RuleType::Distributed) {
      throw new \InvalidArgumentException('Only distributed rows can be allocated.');
    }

    $weights = match ($rule->basis) {
      DistributionBasis::Equal => array_fill_keys($rule->targetParagraphIds, 1.0),
      DistributionBasis::Manual => $rule->manualWeights,
      default => $this->validatedBasisWeights($rule->targetParagraphIds, $basisValues),
    };

    $weightTotal = array_sum($weights);
    if ($weightTotal <= 0) {
      throw new \InvalidArgumentException('Distribution basis must have a positive total.');
    }

    $sourceAmount = $source->directCost();
    $allocations = [];
    $allocated = 0.0;
    $lastTarget = end($rule->targetParagraphIds);

    foreach ($rule->targetParagraphIds as $targetId) {
      if ($targetId === $lastTarget) {
        $amount = $sourceAmount - $allocated;
      }
      else {
        $amount = $sourceAmount * (($weights[$targetId] ?? 0.0) / $weightTotal);
        $allocated += $amount;
      }
      $allocations[$targetId] = $amount;
    }

    return $allocations;
  }

  /**
   * @param list<string> $targets
   * @param array<string, float> $basisValues
   * @return array<string, float>
   */
  private function validatedBasisWeights(array $targets, array $basisValues): array {
    $weights = [];
    foreach ($targets as $targetId) {
      $value = (float) ($basisValues[$targetId] ?? 0.0);
      if ($value < 0) {
        throw new \InvalidArgumentException('Distribution basis values cannot be negative.');
      }
      $weights[$targetId] = $value;
    }
    return $weights;
  }

}
