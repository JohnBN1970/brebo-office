<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Financial buckets for a calculation version.
 *
 * Base, allowance and adjustable rows form the current priced scope. Options
 * remain visible but are excluded from the base total. Notes are non-financial.
 * Distributed rows are included in direct cost but flagged separately so the
 * UI/output layer can suppress or relocate their visible presentation.
 */
final readonly class CalculationTotals {

  public function __construct(
    public float $base = 0.0,
    public float $allowances = 0.0,
    public float $adjustable = 0.0,
    public float $distributed = 0.0,
    public float $options = 0.0,
  ) {}

  public function pricedScope(): float {
    return $this->base + $this->allowances + $this->adjustable + $this->distributed;
  }

  public function includingOptions(): float {
    return $this->pricedScope() + $this->options;
  }

  /** @return array<string, float> */
  public function toArray(): array {
    return [
      'base' => $this->base,
      'allowances' => $this->allowances,
      'adjustable' => $this->adjustable,
      'distributed' => $this->distributed,
      'options' => $this->options,
      'priced_scope' => $this->pricedScope(),
      'including_options' => $this->includingOptions(),
    ];
  }

}
