<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Version-bound calculation parameters, independent from document output.
 */
final readonly class CalculationParameters {

  public function __construct(
    public string $pricingMode = 'closed',
    public string $commercialMethod = 'tail_costs',
    public float $generalCostPct = 0.0,
    public float $riskPct = 0.0,
    public float $profitPct = 0.0,
    public float $singleMarginPct = 0.0,
    public float $commercialAdjustment = 0.0,
    public ?string $priceDate = NULL,
    public ?string $priceLevel = NULL,
  ) {
    if (!in_array($this->pricingMode, ['open', 'semi_open', 'closed', 'cost_plus'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported pricing mode.');
    }
    if (!in_array($this->commercialMethod, ['tail_costs', 'single_margin'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported commercial method.');
    }
    foreach ([$this->generalCostPct, $this->riskPct, $this->profitPct, $this->singleMarginPct] as $percentage) {
      if ($percentage < 0) {
        throw new \InvalidArgumentException('Percentages cannot be negative.');
      }
    }
  }

}
