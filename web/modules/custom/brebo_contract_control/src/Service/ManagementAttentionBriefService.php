<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Converts forecast and current risk into a short management attention brief. */
final class ManagementAttentionBriefService {

  /** @return array<string, mixed> */
  public function build(array $headline, array $forecast): array {
    if (!($forecast['available'] ?? FALSE)) {
      return [
        'level' => 'info',
        'title' => 'Nog onvoldoende historie voor betrouwbare vooruitblik',
        'message' => 'BREBO verzamelt managementsnapshots. Zodra een vergelijkbare vorige periode beschikbaar is, verschijnt hier automatisch een 21-dagen vooruitblik.',
        'priority_action_key' => NULL,
      ];
    }

    $metrics = (array) ($forecast['metrics'] ?? []);
    $priority = $this->priority($metrics);
    if ($priority === NULL) {
      return [
        'level' => 'ok',
        'title' => 'Geen verslechterende kerntrend gedetecteerd',
        'message' => 'De huidige 21-dagenprojectie laat geen verslechtering zien in de primaire control-KPI’s.',
        'priority_action_key' => NULL,
      ];
    }

    [$key, $metric] = $priority;
    $label = $this->label($key);
    $forecastValue = $this->formatValue($key, (float) ($metric['forecast'] ?? 0));
    $currentValue = $this->formatValue($key, (float) ($metric['current'] ?? 0));

    return [
      'level' => in_array($key, ['blocked_payment_value', 'critical_controller_cases', 'portfolio_risk_score'], TRUE) ? 'critical' : 'attention',
      'title' => $label . ' ontwikkelt ongunstig',
      'message' => 'Nu ' . $currentValue . '; bij gelijkblijvend tempo circa ' . $forecastValue . ' binnen ' . (int) ($forecast['horizon_days'] ?? 21) . ' dagen. Aanbevolen: open de gekoppelde managementacties en behandel eerst de beïnvloedbare bronrecords.',
      'priority_action_key' => $this->actionKey($key),
      'metric_key' => $key,
      'confidence' => (string) ($forecast['confidence'] ?? 'low'),
    ];
  }

  /** @param array<string, array<string, mixed>> $metrics
   *  @return array{0:string,1:array<string,mixed>}|null
   */
  private function priority(array $metrics): ?array {
    $weights = [
      'critical_controller_cases' => 100,
      'blocked_payment_value' => 90,
      'portfolio_risk_score' => 80,
      'overdue_contract_obligations' => 70,
      'controller_case_exposure' => 60,
      'suppliers_below_c_rating' => 50,
    ];
    $best = NULL;
    $bestScore = -1;
    foreach ($weights as $key => $weight) {
      $metric = (array) ($metrics[$key] ?? []);
      if (($metric['risk_direction'] ?? 'stable') !== 'worse') { continue; }
      $magnitude = abs((float) ($metric['projected_delta'] ?? 0));
      $score = $weight + min(20, $magnitude > 0 ? log10($magnitude + 1) * 5 : 0);
      if ($score > $bestScore) { $best = [$key, $metric]; $bestScore = $score; }
    }
    return $best;
  }

  private function label(string $key): string {
    return match ($key) {
      'blocked_payment_value' => 'Betalingsblokkade',
      'controller_case_exposure' => 'Controller-blootstelling',
      'critical_controller_cases' => 'Kritieke controllerdossiers',
      'overdue_contract_obligations' => 'Verlopen contractverplichtingen',
      'portfolio_risk_score' => 'Portefeuillerisico',
      'suppliers_below_c_rating' => 'Leveranciersrisico',
      default => $key,
    };
  }

  private function actionKey(string $key): ?string {
    return match ($key) {
      'blocked_payment_value' => 'blocked_payments',
      'controller_case_exposure', 'critical_controller_cases' => 'critical_cases',
      'overdue_contract_obligations' => 'overdue_obligations',
      'portfolio_risk_score' => 'portfolio_risk',
      'suppliers_below_c_rating' => 'supplier_risk',
      default => NULL,
    };
  }

  private function formatValue(string $key, float $value): string {
    return in_array($key, ['blocked_payment_value', 'controller_case_exposure'], TRUE)
      ? 'EUR ' . number_format($value, 0, ',', '.')
      : number_format($value, 0, ',', '.');
  }
}
