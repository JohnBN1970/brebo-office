<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationParameters;

/**
 * Builds a BREBO sales-price indication from the canonical kozijn cost engine.
 *
 * Commercial percentages remain explicit CalculationParameters. This service
 * does not invent or own margin policy.
 */
final class KozijnCommercialPriceService {

  public function __construct(
    private readonly KozijnPriceEngine $priceEngine,
    private readonly CommercialCalculator $commercialCalculator,
  ) {}

  /** @param array<string,mixed> $configuration */
  public function calculate(array $configuration, CalculationParameters $parameters): array {
    $estimate = $this->priceEngine->estimate($configuration);
    if (($estimate['supported'] ?? FALSE) !== TRUE) {
      return [
        'status' => 'insufficient_calibration',
        'model_version' => $estimate['model_version'] ?? KozijnPriceEngine::MODEL_VERSION,
        'reason' => $estimate['reason'] ?? 'unsupported',
      ];
    }

    $low = $this->commercialCalculator->calculate((float) $estimate['net_purchase_low'], $parameters);
    $expected = $this->commercialCalculator->calculate((float) $estimate['net_purchase_expected'], $parameters);
    $high = $this->commercialCalculator->calculate((float) $estimate['net_purchase_high'], $parameters);

    $publicLow = round($low->salesPrice, 2);
    $publicExpected = round($expected->salesPrice, 2);
    $publicHigh = round($high->salesPrice, 2);
    if ($publicLow <= 0 || $publicExpected <= 0 || $publicHigh <= 0) {
      return [
        'status' => 'insufficient_calibration',
        'model_version' => $estimate['model_version'],
        'reason' => 'non_positive_public_price_band',
      ];
    }

    return [
      'status' => 'calculated',
      'model_version' => $estimate['model_version'],
      'reliability' => $estimate['reliability'],
      'internal' => [
        'cost_estimate' => $estimate,
        'commercial_expected' => $expected->toArray(),
      ],
      'public' => [
        'status' => 'indicative',
        'currency' => 'EUR',
        'expected' => $publicExpected,
        'low' => $publicLow,
        'high' => $publicHigh,
        'reliability' => $estimate['reliability'],
        'model_version' => $estimate['model_version'],
        'disclaimer' => 'Prijsindicatie op basis van de bekende configuratie; definitieve prijs volgt na technische en commerciële controle door BREBO.',
      ],
    ];
  }

}
