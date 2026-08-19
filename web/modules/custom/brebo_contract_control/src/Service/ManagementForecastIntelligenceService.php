<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Conservative forward projection from verified month-over-month control trends. */
final class ManagementForecastIntelligenceService {

  /** @return array<string, mixed> */
  public function forecast(array $headline, array $trend, int $horizonDays = 21): array {
    if (!($trend['available'] ?? FALSE)) {
      return ['available' => FALSE, 'horizon_days' => $horizonDays, 'message' => 'Forecast vereist minimaal een vergelijkbare vorige periode.'];
    }

    $metrics = [];
    foreach ((array) ($trend['metrics'] ?? []) as $key => $metric) {
      $current = (float) ($headline[$key] ?? $metric['current'] ?? 0);
      $monthlyDelta = (float) ($metric['delta'] ?? 0);
      $projectedDelta = $monthlyDelta * ($horizonDays / 30);
      $forecast = max(0, $current + $projectedDelta);
      $metrics[$key] = [
        'current' => round($current, 2),
        'forecast' => round($forecast, 2),
        'projected_delta' => round($projectedDelta, 2),
        'risk_direction' => (string) ($metric['risk_direction'] ?? 'stable'),
        'confidence' => 'low',
        'method' => 'linear_month_over_month_projection',
      ];
    }

    $worsening = array_filter($metrics, static fn(array $m): bool => $m['risk_direction'] === 'worse');
    return [
      'available' => TRUE,
      'horizon_days' => $horizonDays,
      'confidence' => 'low',
      'method' => 'lineaire projectie op basis van maand-op-maand verandering',
      'warning' => 'Forecast is een vroegsignaal en geen begroting of zekerheid. Meer historische snapshots verhogen later de betrouwbaarheid.',
      'worsening_metric_count' => count($worsening),
      'metrics' => $metrics,
    ];
  }
}
