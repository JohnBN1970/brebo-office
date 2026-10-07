<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\brebo_procurement_control\Contract\ProcurementControlRepositoryInterface;

/**
 * Records outcomes and explains where procurement predictions were wrong.
 *
 * Learning is advisory only: this service never changes model parameters.
 */
final class ProcurementOutcomeLearningService {

  public function __construct(private readonly ProcurementControlRepositoryInterface $repository) {}

  /** @return array<string, mixed> */
  public function record(int $decisionId, float $purchaseCost, float $failureCost, float $delayCost, float $warrantyCost, float $otherCost, int $recordedBy, ?string $assessment = NULL, ?int $now = NULL): array {
    $now ??= time();
    $decision = $this->repository->decision($decisionId);
    if (!$decision) {
      throw new \InvalidArgumentException('Onbekende inkoopbeslissing.');
    }

    foreach ([$purchaseCost, $failureCost, $delayCost, $warrantyCost, $otherCost] as $value) {
      if ($value < 0) {
        throw new \InvalidArgumentException('Werkelijke kosten mogen niet negatief zijn.');
      }
    }

    $actual = $purchaseCost + $failureCost + $delayCost + $warrantyCost + $otherCost;
    $predicted = (float) $decision['selected_economic_cost'];
    $error = $actual - $predicted;
    $wasOverride = strcasecmp((string) $decision['selected_supplier'], (string) $decision['recommended_supplier']) !== 0;
    $tolerance = max(500.0, $predicted * 0.02);

    if ($wasOverride) {
      $counterfactual = (float) $decision['recommended_economic_cost'];
      $outcome = $actual <= $counterfactual ? 'human_override_won' : 'human_override_lost';
    }
    else {
      $outcome = abs($error) <= $tolerance ? 'model_confirmed' : ($error > 0 ? 'model_underestimated' : 'model_overestimated');
    }

    $this->repository->upsertOutcome($decisionId, [
      'actual_purchase_cost' => round($purchaseCost, 2),
      'actual_failure_cost' => round($failureCost, 2),
      'actual_delay_cost' => round($delayCost, 2),
      'actual_warranty_cost' => round($warrantyCost, 2),
      'actual_other_cost' => round($otherCost, 2),
      'actual_economic_cost' => round($actual, 2),
      'model_error' => round($error, 2),
      'outcome' => $outcome,
      'assessment' => $assessment,
      'recorded_by' => $recordedBy,
      'recorded_at' => $now,
    ]);

    return [
      'decision_id' => $decisionId,
      'outcome' => $outcome,
      'predicted_economic_cost' => round($predicted, 2),
      'actual_economic_cost' => round($actual, 2),
      'model_error' => round($error, 2),
      'error_pct' => $predicted > 0 ? round(($error / $predicted) * 100, 2) : 0.0,
      'cost_drivers' => $this->drivers($failureCost, $delayCost, $warrantyCost, $otherCost),
      'parameter_change_allowed' => FALSE,
    ];
  }

  /** @return array<string, mixed> */
  public function diagnostics(): array {
    $rows = $this->repository->outcomes();
    $count = count($rows);
    $absoluteError = 0.0;
    $outcomes = [];
    $drivers = ['failure_cost' => 0.0, 'delay_cost' => 0.0, 'warranty_cost' => 0.0, 'other_cost' => 0.0];
    foreach ($rows as $row) {
      $absoluteError += abs((float) $row['model_error']);
      $outcomes[(string) $row['outcome']] = ($outcomes[(string) $row['outcome']] ?? 0) + 1;
      foreach ($drivers as $key => $_) {
        $column = 'actual_' . $key;
        $drivers[$key] += (float) ($row[$column] ?? 0);
      }
    }
    arsort($drivers);

    return [
      'observations' => $count,
      'mean_absolute_error' => $count > 0 ? round($absoluteError / $count, 2) : 0.0,
      'outcomes' => $outcomes,
      'cost_driver_totals' => array_map(static fn(float $v): float => round($v, 2), $drivers),
      'dominant_missed_driver' => array_key_first($drivers),
      'learning_status' => $count >= 25 ? 'voldoende_voor_parameterreview' : 'eerst_meer_bewijs_verzamelen',
      'governance' => 'Modelparameters wijzigen nooit automatisch; wijzigingen vereisen een geversioneerde parameterreview en menselijke goedkeuring.',
    ];
  }

  /** @return array<int, array<string, float|string>> */
  private function drivers(float $failure, float $delay, float $warranty, float $other): array {
    $drivers = [
      'failure_cost' => $failure,
      'delay_cost' => $delay,
      'warranty_cost' => $warranty,
      'other_cost' => $other,
    ];
    arsort($drivers);
    $result = [];
    foreach ($drivers as $driver => $value) {
      if ($value > 0) {
        $result[] = ['driver' => $driver, 'cost' => round($value, 2)];
      }
    }
    return $result;
  }
}
