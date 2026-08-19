<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\Core\Database\Connection;

/** Learns from actual economic outcomes of procurement decisions. */
final class ProcurementOutcomeService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function record(int $decisionId, float $purchaseCost, float $failureCost, float $delayCost, float $warrantyCost, float $otherCost, int $recordedBy, ?string $assessment = NULL, ?int $now = NULL): array {
    $now ??= time();
    $decision = $this->database->select('brebo_procurement_decision', 'd')->fields('d')
      ->condition('id', $decisionId)->execute()->fetchAssoc();
    if (!$decision) {
      throw new \InvalidArgumentException('Onbekende inkoopbeslissing.');
    }
    if ($this->database->select('brebo_procurement_outcome', 'o')->condition('decision_id', $decisionId)->countQuery()->execute()->fetchField()) {
      throw new \LogicException('Voor deze inkoopbeslissing bestaat al een nacalculatie.');
    }

    $actual = $purchaseCost + $failureCost + $delayCost + $warrantyCost + $otherCost;
    $predicted = (float) $decision['selected_economic_cost'];
    $modelError = $actual - $predicted;
    $deviation = (string) $decision['decision_status'] === 'approved_deviation';
    $recommendedCost = (float) $decision['recommended_economic_cost'];

    $outcome = match (TRUE) {
      !$deviation && $actual <= $predicted => 'model_confirmed',
      !$deviation => 'model_underestimated',
      $deviation && $actual <= $recommendedCost => 'human_override_won',
      $deviation && $actual > $recommendedCost => 'human_override_lost',
      default => 'neutral',
    };

    $id = (int) $this->database->insert('brebo_procurement_outcome')->fields([
      'decision_id' => $decisionId,
      'actual_purchase_cost' => round($purchaseCost, 2),
      'actual_failure_cost' => round($failureCost, 2),
      'actual_delay_cost' => round($delayCost, 2),
      'actual_warranty_cost' => round($warrantyCost, 2),
      'actual_other_cost' => round($otherCost, 2),
      'actual_economic_cost' => round($actual, 2),
      'model_error' => round($modelError, 2),
      'outcome' => $outcome,
      'assessment' => $assessment,
      'recorded_by' => $recordedBy,
      'recorded_at' => $now,
    ])->execute();

    return [
      'outcome_id' => $id,
      'decision_id' => $decisionId,
      'outcome' => $outcome,
      'predicted_economic_cost' => round($predicted, 2),
      'actual_economic_cost' => round($actual, 2),
      'model_error' => round($modelError, 2),
      'human_override' => $deviation,
      'economic_delta_vs_recommended_prediction' => round($actual - $recommendedCost, 2),
    ];
  }

  /** @return array<string, mixed> */
  public function learningSummary(): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_outcome')) {
      return [];
    }
    $rows = $this->database->select('brebo_procurement_outcome', 'o')->fields('o')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $counts = ['model_confirmed' => 0, 'model_underestimated' => 0, 'human_override_won' => 0, 'human_override_lost' => 0, 'neutral' => 0];
    $absoluteError = 0.0;
    foreach ($rows as $row) {
      $counts[$row['outcome']] = ($counts[$row['outcome']] ?? 0) + 1;
      $absoluteError += abs((float) $row['model_error']);
    }
    $overrides = $counts['human_override_won'] + $counts['human_override_lost'];
    return [
      'observations' => count($rows),
      'outcomes' => $counts,
      'human_override_success_pct' => $overrides > 0 ? round(($counts['human_override_won'] / $overrides) * 100, 1) : NULL,
      'mean_absolute_model_error' => count($rows) > 0 ? round($absoluteError / count($rows), 2) : NULL,
    ];
  }
}
