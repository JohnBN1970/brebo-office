<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Immutable result of commercial calculation buildup. */
final readonly class CommercialResult {

  public function __construct(
    public float $directCost,
    public float $generalCost,
    public float $risk,
    public float $profit,
    public float $singleMargin,
    public float $commercialAdjustment,
    public float $salesPrice,
  ) {}

  /** @return array<string, float> */
  public function toArray(): array {
    return [
      'direct_cost' => $this->directCost,
      'general_cost' => $this->generalCost,
      'risk' => $this->risk,
      'profit' => $this->profit,
      'single_margin' => $this->singleMargin,
      'commercial_adjustment' => $this->commercialAdjustment,
      'sales_price' => $this->salesPrice,
    ];
  }

}
