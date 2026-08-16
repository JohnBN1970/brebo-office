<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Immutable direct-cost composition of one calculation row.
 */
final readonly class CostBreakdown {

  public function __construct(
    public float $labour = 0.0,
    public float $material = 0.0,
    public float $equipment = 0.0,
    public float $subcontracting = 0.0,
    public float $other = 0.0,
  ) {
    foreach ($this->toArray() as $amount) {
      if ($amount < 0) {
        throw new \InvalidArgumentException('Cost components cannot be negative.');
      }
    }
  }

  public function directCost(): float {
    return $this->labour + $this->material + $this->equipment + $this->subcontracting + $this->other;
  }

  /** @return array<string, float> */
  public function toArray(): array {
    return [
      'labour' => $this->labour,
      'material' => $this->material,
      'equipment' => $this->equipment,
      'subcontracting' => $this->subcontracting,
      'other' => $this->other,
    ];
  }

}
