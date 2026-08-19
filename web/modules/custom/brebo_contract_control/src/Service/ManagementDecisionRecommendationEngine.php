<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Ranks transparent scenarios on impact, effort, speed, value and confidence. */
final class ManagementDecisionRecommendationEngine {

  /** @return array<string, mixed> */
  public function recommend(array $scenarioAnalysis): array {
    if (!($scenarioAnalysis['available'] ?? FALSE)) {
      return ['available' => FALSE, 'message' => 'Beslisadvies vereist beschikbare scenarioanalyse.'];
    }

    $profiles = [
      'do_nothing' => ['effort' => 100, 'speed' => 100, 'financial_value' => 0],
      'clear_overdue_obligations' => ['effort' => 65, 'speed' => 90, 'financial_value' => 85],
      'supplier_extra_review' => ['effort' => 75, 'speed' => 75, 'financial_value' => 60],
      'controller_cases_priority' => ['effort' => 70, 'speed' => 95, 'financial_value' => 90],
    ];

    $ranked = [];
    foreach ((array) ($scenarioAnalysis['scenarios'] ?? []) as $scenario) {
      $key = (string) ($scenario['key'] ?? '');
      $profile = $profiles[$key] ?? ['effort' => 50, 'speed' => 50, 'financial_value' => 50];
      $impact = (float) ($scenario['impact_score'] ?? 0);
      $confidence = match ((string) ($scenario['confidence'] ?? 'low')) { 'high' => 100, 'medium' => 70, default => 40 };
      $score = ($impact * 0.40) + ($profile['effort'] * 0.15) + ($profile['speed'] * 0.20) + ($profile['financial_value'] * 0.15) + ($confidence * 0.10);
      $scenario['decision_score'] = round($score, 1);
      $scenario['decision_factors'] = [
        'risk_reduction_impact' => $impact,
        'effort_efficiency' => $profile['effort'],
        'speed' => $profile['speed'],
        'financial_value' => $profile['financial_value'],
        'confidence_score' => $confidence,
      ];
      $ranked[] = $scenario;
    }

    usort($ranked, static fn(array $a, array $b): int => $b['decision_score'] <=> $a['decision_score']);
    $recommended = $ranked[0] ?? NULL;
    if ($recommended === NULL || ($recommended['key'] ?? '') === 'do_nothing') {
      return ['available' => TRUE, 'recommendation' => 'Geen interventie heeft momenteel aantoonbaar voldoende voordeel boven niets doen.', 'recommended_scenario' => $recommended, 'ranking' => $ranked, 'confidence' => 'low'];
    }

    return [
      'available' => TRUE,
      'recommendation' => 'Aanbevolen vandaag: ' . (string) $recommended['title'] . '. Hoogste gewogen combinatie van risicoreductie, snelheid, financiële waarde en benodigde managementinspanning.',
      'recommended_scenario' => $recommended,
      'ranking' => $ranked,
      'confidence' => (string) ($recommended['confidence'] ?? 'low'),
      'governance' => 'Het advies rangschikt transparante scenario’s. De directie blijft beslissingsbevoegd; aannames en confidence moeten zichtbaar blijven.',
    ];
  }
}
