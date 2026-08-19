<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Measures whether management interventions stay effective over time. */
final class ControlEffectivenessIntelligenceService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function analyze(): array {
    if (!$this->database->schema()->tableExists('brebo_management_action')) {
      return ['observations' => 0, 'status' => 'no_data', 'action_types' => []];
    }

    $rows = $this->database->select('brebo_management_action', 'a')->fields('a')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $groups = [];
    foreach ($rows as $row) {
      $key = (string) $row['action_key'];
      $groups[$key] ??= [
        'actions' => 0,
        'resolved' => 0,
        'verified_closed' => 0,
        'reopened' => 0,
        'estimated_exposure' => 0.0,
        'prevented_value' => 0.0,
      ];
      $groups[$key]['actions']++;
      if (in_array($row['status'], ['resolved', 'verified_closed', 'reopened'], TRUE)) {
        $groups[$key]['resolved']++;
      }
      if ($row['status'] === 'verified_closed') {
        $groups[$key]['verified_closed']++;
      }
      if ($row['status'] === 'reopened') {
        $groups[$key]['reopened']++;
      }

      $context = json_decode((string) ($row['context_json'] ?? '{}'), TRUE) ?: [];
      $headline = (array) ($context['headline'] ?? []);
      $exposure = $this->exposureForKey($key, $headline);
      $groups[$key]['estimated_exposure'] += $exposure;
      if ($row['status'] === 'verified_closed') {
        $groups[$key]['prevented_value'] += $exposure;
      }
    }

    $result = [];
    foreach ($groups as $key => $stats) {
      $closed = max(1, (int) $stats['resolved']);
      $verifiedRate = ((int) $stats['verified_closed'] / $closed) * 100;
      $recurrenceRate = ((int) $stats['reopened'] / $closed) * 100;
      $result[] = [
        'action_key' => $key,
        'actions' => (int) $stats['actions'],
        'resolved' => (int) $stats['resolved'],
        'verified_closed' => (int) $stats['verified_closed'],
        'reopened' => (int) $stats['reopened'],
        'verified_effectiveness_pct' => round($verifiedRate, 1),
        'recurrence_pct' => round($recurrenceRate, 1),
        'estimated_exposure' => round((float) $stats['estimated_exposure'], 2),
        'estimated_prevented_value' => round((float) $stats['prevented_value'], 2),
        'confidence' => $this->confidence((int) $stats['actions']),
        'effectiveness' => $this->effectiveness($stats['actions'], $verifiedRate, $recurrenceRate),
      ];
    }

    usort($result, static function (array $a, array $b): int {
      $rank = ['ineffective' => 3, 'weak' => 2, 'effective' => 1, 'insufficient_data' => 0];
      return ($rank[$b['effectiveness']] ?? 0) <=> ($rank[$a['effectiveness']] ?? 0);
    });

    return [
      'observations' => count($rows),
      'action_types' => $result,
      'total_estimated_prevented_value' => round(array_sum(array_column($result, 'estimated_prevented_value')), 2),
      'governance' => 'Voorkomen waarde is een controlschatting op basis van vastgelegde blootstelling en mag niet als gerealiseerde besparing worden geboekt zonder financiële validatie.',
    ];
  }

  /** @param array<string, mixed> $headline */
  private function exposureForKey(string $key, array $headline): float {
    return match ($key) {
      'critical_cases' => (float) ($headline['controller_case_exposure'] ?? 0),
      'blocked_payments' => (float) ($headline['blocked_payment_value'] ?? 0),
      'portfolio_risk' => max(0.0, (float) ($headline['portfolio_risk_score'] ?? 0) * 1000),
      'supplier_risk' => max(0.0, (float) ($headline['suppliers_below_c_rating'] ?? 0) * 5000),
      'overdue_obligations' => max(0.0, (float) ($headline['overdue_contract_obligations'] ?? 0) * 1000),
      default => 0.0,
    };
  }

  private function confidence(int $observations): string {
    return match (TRUE) {
      $observations >= 20 => 'hoog',
      $observations >= 10 => 'middel',
      $observations >= 5 => 'laag',
      default => 'onvoldoende',
    };
  }

  private function effectiveness(int $observations, float $verifiedRate, float $recurrenceRate): string {
    if ($observations < 5) {
      return 'insufficient_data';
    }
    if ($verifiedRate >= 80.0 && $recurrenceRate <= 10.0) {
      return 'effective';
    }
    if ($verifiedRate >= 50.0 && $recurrenceRate <= 30.0) {
      return 'weak';
    }
    return 'ineffective';
  }
}
