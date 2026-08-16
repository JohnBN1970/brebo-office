<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Defines how a distributed row is allocated without changing its source cost.
 */
final readonly class DistributionRule {

  /**
   * @param list<string> $targetParagraphIds
   * @param array<string, float> $manualWeights
   */
  public function __construct(
    public int $sourceLineId,
    public DistributionBasis $basis,
    public array $targetParagraphIds,
    public array $manualWeights = [],
  ) {
    if ($this->sourceLineId <= 0 || $this->targetParagraphIds === []) {
      throw new \InvalidArgumentException('Distribution requires a source row and at least one target paragraph.');
    }
    if ($this->basis === DistributionBasis::Manual) {
      foreach ($this->targetParagraphIds as $targetId) {
        if (!array_key_exists($targetId, $this->manualWeights) || $this->manualWeights[$targetId] < 0) {
          throw new \InvalidArgumentException('Manual distribution requires a non-negative weight for every target.');
        }
      }
      if (array_sum($this->manualWeights) <= 0) {
        throw new \InvalidArgumentException('Manual distribution weights must have a positive total.');
      }
    }
  }
}
