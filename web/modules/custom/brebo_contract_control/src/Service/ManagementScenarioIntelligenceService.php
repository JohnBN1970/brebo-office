<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Builds transparent what-if scenarios from current forecast and interventions. */
final class ManagementScenarioIntelligenceService {

  /** @return array<string, mixed> */
  public function scenarios(array $headline, array $forecast): array {
    if (!($forecast['available'] ?? FALSE)) {
      return ['available' => FALSE, 'message' => 'Scenarioanalyse vereist een beschikbare forecast.'];
    }

    $metrics = (array) ($forecast['metrics'] ?? []);
    $base = $this->snapshot($metrics);
    $scenarios = [
      [
        'key' => 'do_nothing',
        'title' => 'Niets doen',
        'assumptions' => ['Huidige trend zet lineair door gedurende de forecastperiode.'],
        'projected' => $base,
      ],
      [
        'key' => 'clear_overdue_obligations',
        'title' => 'Verlopen contractverplichtingen vandaag wegwerken',
        'assumptions' => ['Alle huidige verlopen verplichtingen worden opgelost.', 'Effect op betalingsblokkade wordt voorzichtig op 15% reductie van de geprojecteerde stijging gezet.'],
        'projected' => $this->adjust($base, [
          'overdue_contract_obligations' => 0,
          'blocked_payment_value' => max(0, ($base['blocked_payment_value'] ?? 0) - max(0, (($base['blocked_payment_value'] ?? 0) - (float) ($headline['blocked_payment_value'] ?? 0)) * 0.15)),
        ]),
      ],
      [
        'key' => 'supplier_extra_review',
        'title' => 'Risicoleveranciers tijdelijk onder extra review',
        'assumptions' => ['Nieuwe opdrachten aan risicoleveranciers krijgen extra review.', 'Scenario veronderstelt 25% reductie van de geprojecteerde toename in leveranciersrisico.'],
        'projected' => $this->adjust($base, [
          'suppliers_below_c_rating' => max(0, ($base['suppliers_below_c_rating'] ?? 0) - max(1, round(max(0, ($base['suppliers_below_c_rating'] ?? 0) - (float) ($headline['suppliers_below_c_rating'] ?? 0)) * 0.25))),
        ]),
      ],
      [
        'key' => 'controller_cases_priority',
        'title' => 'Kritieke controllerdossiers met voorrang behandelen',
        'assumptions' => ['Hoog/kritieke dossiers worden binnen 24 uur opgepakt.', 'Scenario reduceert de geprojecteerde blootstelling indicatief met 20%.'],
        'projected' => $this->adjust($base, [
          'controller_case_exposure' => max(0, ($base['controller_case_exposure'] ?? 0) * 0.80),
          'critical_controller_cases' => max(0, ($base['critical_controller_cases'] ?? 0) - 1),
        ]),
      ],
    ];

    foreach ($scenarios as &$scenario) {
      $scenario['impact_score'] = $this->impactScore($base, (array) $scenario['projected']);
      $scenario['confidence'] = 'low';
    }
    unset($scenario);
    usort($scenarios, static fn(array $a, array $b): int => $b['impact_score'] <=> $a['impact_score']);

    return [
      'available' => TRUE,
      'confidence' => 'low',
      'scenarios' => $scenarios,
      'best_scenario_key' => $scenarios[0]['key'] ?? 'do_nothing',
      'governance' => 'Scenario’s zijn transparante what-if berekeningen op basis van expliciete aannames. Ze zijn beslisondersteuning en geen voorspelde zekerheid.',
    ];
  }

  /** @return array<string, float> */
  private function snapshot(array $metrics): array {
    $out = [];
    foreach ($metrics as $key => $metric) { $out[$key] = (float) ($metric['forecast'] ?? 0); }
    return $out;
  }

  /** @param array<string, float|int> $changes
   *  @return array<string, float>
   */
  private function adjust(array $base, array $changes): array {
    foreach ($changes as $key => $value) { $base[$key] = (float) $value; }
    return $base;
  }

  private function impactScore(array $base, array $projected): int {
    $weights = [
      'blocked_payment_value' => 0.00005,
      'controller_case_exposure' => 0.00005,
      'critical_controller_cases' => 15,
      'overdue_contract_obligations' => 8,
      'portfolio_risk_score' => 2,
      'suppliers_below_c_rating' => 5,
    ];
    $score = 0.0;
    foreach ($weights as $key => $weight) {
      $score += max(0, (float) ($base[$key] ?? 0) - (float) ($projected[$key] ?? 0)) * $weight;
    }
    return (int) round(min(100, $score));
  }
}
