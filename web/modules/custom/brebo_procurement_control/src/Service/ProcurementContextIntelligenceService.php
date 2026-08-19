<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Learns in which procurement contexts human review adds the most value.
 */
final class ProcurementContextIntelligenceService {

  public function __construct(private readonly Connection $database) {}

  /**
   * Store an auditable decision-time context snapshot.
   *
   * @param array<string, mixed> $context
   */
  public function captureContext(int $decisionId, array $context, ?int $now = NULL): void {
    $allowed = [
      'work_category',
      'supplier_confidence',
      'supplier_rating',
      'bid_count',
      'economic_delta_pct',
      'warranty_intensity',
      'planning_criticality',
      'quality_criticality',
      'contract_complexity',
      'new_supplier',
    ];
    $clean = [];
    foreach ($allowed as $key) {
      if (array_key_exists($key, $context)) {
        $clean[$key] = $context[$key];
      }
    }
    $this->database->merge('brebo_procurement_decision_context')->key(['decision_id' => $decisionId])->fields([
      'context_json' => json_encode($clean, JSON_THROW_ON_ERROR),
      'captured_at' => $now ?? time(),
    ])->execute();
  }

  /** @return array<string, mixed> */
  public function analyze(): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_decision_context')) {
      return ['observations' => 0, 'segments' => [], 'status' => 'no_context_data'];
    }

    $query = $this->database->select('brebo_procurement_decision_context', 'c');
    $query->join('brebo_procurement_decision', 'd', 'd.id = c.decision_id');
    $query->join('brebo_procurement_outcome', 'o', 'o.decision_id = c.decision_id');
    $query->fields('c', ['context_json']);
    $query->fields('d', ['selected_supplier', 'recommended_supplier']);
    $query->fields('o', ['outcome', 'model_error']);
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $segments = [];
    foreach ($rows as $row) {
      $context = json_decode((string) $row['context_json'], TRUE) ?: [];
      foreach ($this->segmentKeys($context) as $segment) {
        $segments[$segment] ??= ['observations' => 0, 'override_wins' => 0, 'override_losses' => 0, 'model_abs_error' => 0.0];
        $segments[$segment]['observations']++;
        $segments[$segment]['model_abs_error'] += abs((float) $row['model_error']);
        if ($row['outcome'] === 'human_override_won') {
          $segments[$segment]['override_wins']++;
        }
        elseif ($row['outcome'] === 'human_override_lost') {
          $segments[$segment]['override_losses']++;
        }
      }
    }

    $result = [];
    foreach ($segments as $segment => $stats) {
      $overrideCount = $stats['override_wins'] + $stats['override_losses'];
      $winRate = $overrideCount > 0 ? ($stats['override_wins'] / $overrideCount) * 100 : 0.0;
      $result[] = [
        'segment' => $segment,
        'observations' => $stats['observations'],
        'override_observations' => $overrideCount,
        'human_override_win_rate_pct' => round($winRate, 1),
        'mean_model_absolute_error' => round($stats['model_abs_error'] / max(1, $stats['observations']), 2),
        'confidence' => $this->confidence($stats['observations']),
        'recommended_control_mode' => $this->mode($stats['observations'], $overrideCount, $winRate),
      ];
    }

    usort($result, static function (array $a, array $b): int {
      $rank = ['senior_human_review' => 3, 'hybrid_review' => 2, 'model_led' => 1, 'insufficient_data' => 0];
      return ($rank[$b['recommended_control_mode']] ?? 0) <=> ($rank[$a['recommended_control_mode']] ?? 0);
    });

    return [
      'observations' => count($rows),
      'segments' => $result,
      'governance' => 'Contextanalyse adviseert alleen de controlmodus; zij kan geen leverancier selecteren of menselijke goedkeuring overslaan.',
    ];
  }

  /** @param array<string, mixed> $context
   *  @return string[]
   */
  private function segmentKeys(array $context): array {
    $keys = [];
    foreach (['work_category', 'supplier_confidence', 'supplier_rating', 'warranty_intensity', 'planning_criticality', 'quality_criticality', 'contract_complexity'] as $key) {
      if (isset($context[$key]) && $context[$key] !== '') {
        $keys[] = $key . ':' . strtolower((string) $context[$key]);
      }
    }
    if (!empty($context['new_supplier'])) {
      $keys[] = 'supplier:new';
    }
    return $keys ?: ['context:unspecified'];
  }

  private function mode(int $observations, int $overrideCount, float $winRate): string {
    if ($observations < 10 || $overrideCount < 5) {
      return 'insufficient_data';
    }
    if ($winRate >= 65.0) {
      return 'senior_human_review';
    }
    if ($winRate >= 40.0) {
      return 'hybrid_review';
    }
    return 'model_led';
  }

  private function confidence(int $observations): string {
    return match (TRUE) {
      $observations >= 40 => 'hoog',
      $observations >= 20 => 'middel',
      $observations >= 10 => 'laag',
      default => 'onvoldoende',
    };
  }
}
