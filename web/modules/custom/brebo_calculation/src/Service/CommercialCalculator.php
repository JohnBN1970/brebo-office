<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\brebo_calculation\Domain\CommercialResult;

/**
 * Central commercial buildup for a calculation version.
 *
 * Tail costs are applied sequentially so each layer has an explicit base.
 * Single margin is an alternative method and is never stacked with tail costs.
 */
final class CommercialCalculator {

  public function calculate(float $directCost, CalculationParameters $parameters): CommercialResult {
    if ($directCost < 0) {
      throw new \InvalidArgumentException('Direct cost cannot be negative.');
    }

    $generalCost = 0.0;
    $risk = 0.0;
    $profit = 0.0;
    $singleMargin = 0.0;

    if ($parameters->commercialMethod === 'single_margin') {
      $singleMargin = $directCost * ($parameters->singleMarginPct / 100);
      $beforeAdjustment = $directCost + $singleMargin;
    }
    else {
      $generalCost = $directCost * ($parameters->generalCostPct / 100);
      $afterGeneralCost = $directCost + $generalCost;
      $risk = $afterGeneralCost * ($parameters->riskPct / 100);
      $afterRisk = $afterGeneralCost + $risk;
      $profit = $afterRisk * ($parameters->profitPct / 100);
      $beforeAdjustment = $afterRisk + $profit;
    }

    $salesPrice = $beforeAdjustment + $parameters->commercialAdjustment;

    return new CommercialResult(
      directCost: $directCost,
      generalCost: $generalCost,
      risk: $risk,
      profit: $profit,
      singleMargin: $singleMargin,
      commercialAdjustment: $parameters->commercialAdjustment,
      salesPrice: $salesPrice,
    );
  }

}
