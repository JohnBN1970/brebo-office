<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Canonical calculation row attached to a leaf paragraph. */
final readonly class CalculationRow {

  public function __construct(
    public int $legacyLineId,
    public string $paragraphId,
    public RuleType $type,
    public string $description,
    public float $quantity,
    public string $unit,
    public CostBreakdown $unitCosts,
    public int $sortOrder = 0,
    public ?string $locationRef = NULL,
    public ?float $actualQuantity = NULL,
    public ?string $memo = NULL,
  ) {
    if ($this->legacyLineId <= 0) {
      throw new \InvalidArgumentException('A stable legacy calculation-line id is required during migration.');
    }
    if ($this->paragraphId === '') {
      throw new \InvalidArgumentException('A calculation row requires a leaf paragraph.');
    }
    if ($this->quantity < 0 || ($this->actualQuantity !== NULL && $this->actualQuantity < 0)) {
      throw new \InvalidArgumentException('Quantities cannot be negative.');
    }
  }

  public function effectiveQuantity(): float {
    if ($this->type === RuleType::Adjustable && $this->actualQuantity !== NULL) {
      return $this->actualQuantity;
    }
    return $this->quantity;
  }

  public function directCost(): float {
    if ($this->type === RuleType::Note) {
      return 0.0;
    }
    return $this->effectiveQuantity() * $this->unitCosts->directCost();
  }

}
