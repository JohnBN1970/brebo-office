<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Ranks transparent management scenarios on impact, effort, speed and confidence. */
final class ManagementDecisionRecommendationService {

  /** @return array<string, mixed> */
  public function recommend(array $scenarioAnalysis): array {
    if (!($scenarioAnalysis['available'] ?? FALSE)) {
      return ['available' => FALSE, 'message' => 'Beslisadvies vereist beschikbare scenarioanalyse.'];
    }

    $profiles = [
      'do_nothing' => ['effort' => 0, 'speed' => 0, 'financial_value' => 0],
      'clear_overdue_obligations' => ['effort' => 35, 'speed' => 90, 'financial_value' => 80],
      'supplier_extra_review' => ['effort' => 45, 'speed' => 65, 'financial_value' => 55],
      'controller_cases_priority' => ['effort' => 55, 'speed' => 85, 'financial_value' => 90],
    ];

    $ranked = [];
    foreach ((array) ($scenarioAnalysis['scenarios'] ?? []) as $scenario) {
      $key = (string) ($scenario['key'] ?? '');
      $profile = $profiles[$key] ?? ['effort' => 60, 'speed' => 50, 'financial_value' => 50];
      $impact = (float) ($scenario['impact_score'] ?? 0);
      $confidenceFactor = match ((string) ($scenario['confidence'] ?? 'low')) { 'high' => 1.0, 'medium' => 0.8, default => 0.6 };
      $effortEfficiency = $profile['effort'] > 0 ? min(100, ($impact / $profile['effort']) * 100) : 0;
      $score = ($impact * 0.40) + ($profile['speed'] * 0.20) + ($profile['financial_value'] * 0.20) + ($effortEfficiency * 0.20);
      $score *= $confidenceFactor;
      $ranked[] = $scenario + [
        'effort_score' => $profile['effort'],
        'speed_score' => $profile['speed'],
        'financial_value_score' => $profile['financial_value'],
        'effort_efficiency_score' => round($effortEfficiency, 1),
        'decision_score' => round($score, 1),
      ];
    }

    usort($ranked, static fn(array $a, array $b): int => $b['decision_score'] <=> $a['decision_score']);
    $actionable = array_values(array_filter($ranked, static fn(array $s): bool => ($s['key'] ?? '') !== 'do_nothing'));
    $best = $actionable[0] ?? $ranked[0] ?? NULL;

    return [
      'available' => $best !== NULL,
      'recommended_scenario_key' => $best['key'] ?? NULL,
      'recommended_title' => $best['title'] ?? NULL,
      'decision_score' => $best['decision_score'] ?? 0,
      'confidence' => $best['confidence'] ?? 'low',
      'reason' => $best ? 'Hoogste gewogen combinatie van verwachte risicoreductie, snelheid, financiële waarde en benodigde managementinspanning.' : '',
      'ranked_scenarios' => $ranked,
      'governance' => 'De rangschikking is beslisondersteuning. Inspanning, snelheid en financiële waarde zijn transparante startprofielen en moeten later worden gekalibreerd met werkelijke BREBO-resultaten.',
    ];
  }
}
