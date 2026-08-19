<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Ranks recurring control failures by likely root-cause family and evidence.
 *
 * Output is diagnostic, not accusatory: causes remain hypotheses until human
 * review confirms them against source evidence.
 */
final class RootCauseIntelligenceService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function analyze(): array {
    if (!$this->database->schema()->tableExists('brebo_management_action')) {
      return ['status' => 'no_data', 'causes' => [], 'observations' => 0];
    }

    $rows = $this->database->select('brebo_management_action', 'a')->fields('a')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $causes = [];

    foreach ($rows as $row) {
      $context = json_decode((string) ($row['context_json'] ?? ''), TRUE) ?: [];
      foreach ($this->classify($row, $context) as $cause) {
        $key = $cause['code'];
        $causes[$key] ??= [
          'code' => $key,
          'label' => $cause['label'],
          'observations' => 0,
          'reopened' => 0,
          'verified_closed' => 0,
          'severity_points' => 0,
          'evidence' => [],
        ];
        $causes[$key]['observations']++;
        $causes[$key]['reopened'] += in_array((string) $row['status'], ['reopened', 'open'], TRUE) && !empty($row['verified_at']) ? 1 : 0;
        $causes[$key]['verified_closed'] += (string) $row['status'] === 'verified_closed' ? 1 : 0;
        $causes[$key]['severity_points'] += $this->severityPoints((string) ($row['severity'] ?? 'low'));
        $causes[$key]['evidence'][] = [
          'action_id' => (int) $row['id'],
          'action_key' => (string) $row['action_key'],
          'source_type' => (string) $row['source_type'],
          'status' => (string) $row['status'],
        ];
      }
    }

    $result = [];
    foreach ($causes as $cause) {
      $recurrenceRate = $cause['observations'] > 0 ? ($cause['reopened'] / $cause['observations']) * 100 : 0.0;
      $score = min(100, (int) round(
        ($cause['observations'] * 6)
        + ($cause['reopened'] * 12)
        + $cause['severity_points']
      ));
      $result[] = $cause + [
        'score' => $score,
        'recurrence_pct' => round($recurrenceRate, 1),
        'confidence' => $this->confidence((int) $cause['observations']),
        'hypothesis_status' => $cause['observations'] >= 5 ? 'review_warranted' : 'weak_signal',
      ];
    }

    usort($result, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

    return [
      'status' => $result === [] ? 'insufficient_evidence' : 'diagnostic_ready',
      'observations' => count($rows),
      'causes' => $result,
      'top_cause' => $result[0] ?? NULL,
      'governance' => 'Root-cause-uitkomsten zijn hypotheses op basis van herhaling en context; bevestiging vereist brononderzoek en menselijke beoordeling.',
    ];
  }

  /** @param array<string, mixed> $row
   *  @param array<string, mixed> $context
   *  @return array<int, array{code:string,label:string}>
   */
  private function classify(array $row, array $context): array {
    $source = (string) ($row['source_type'] ?? '');
    $key = (string) ($row['action_key'] ?? '');
    $causes = [];

    if (in_array($source, ['contract_obligation', 'payment_control'], TRUE) || $key === 'overdue_obligations') {
      $causes[] = ['code' => 'process_discipline', 'label' => 'Procesdiscipline / opvolging'];
    }
    if ($source === 'supplier_risk' || $key === 'supplier_risk') {
      $causes[] = ['code' => 'supplier_performance', 'label' => 'Leveranciersprestatie'];
    }
    if ($source === 'portfolio_risk' || $key === 'portfolio_risk') {
      $causes[] = ['code' => 'late_risk_intervention', 'label' => 'Te late risico-interventie'];
    }
    if ($source === 'controller_case' || $key === 'critical_cases') {
      $causes[] = ['code' => 'control_design', 'label' => 'Ontwerp of werking beheersmaatregel'];
    }
    if ((int) (($context['headline']['overdue_contract_obligations'] ?? 0)) > 0) {
      $causes[] = ['code' => 'ownership_or_capacity', 'label' => 'Eigenaarschap / capaciteit'];
    }
    if ((int) (($context['headline']['suppliers_below_c_rating'] ?? 0)) > 0) {
      $causes[] = ['code' => 'supplier_selection', 'label' => 'Leveranciersselectie / contractering'];
    }
    if ((int) (($context['headline']['portfolio_risk_score'] ?? 0)) >= 75) {
      $causes[] = ['code' => 'risk_detection_timing', 'label' => 'Signalering of ingrijpen te laat'];
    }
    if ($causes === []) {
      $causes[] = ['code' => 'insufficient_context', 'label' => 'Onvoldoende context / datakwaliteit'];
    }

    $unique = [];
    foreach ($causes as $cause) {
      $unique[$cause['code']] = $cause;
    }
    return array_values($unique);
  }

  private function severityPoints(string $severity): int {
    return match ($severity) {
      'critical' => 10,
      'high' => 6,
      'medium' => 3,
      default => 1,
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
}
