<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Shapes financial phase-gate decisions for dashboards and APIs.
 */
final class FinancialPhaseGatePresenter {

  private const LABELS = [
    'procurement_release' => 'Inkoopvrijgave',
    'execution_start' => 'Start uitvoering',
    'billing_release' => 'Facturatievrijgave',
    'payment_release' => 'Betaalvrijgave',
    'project_closeout' => 'Projectafsluiting',
  ];

  public function __construct(private readonly FinancialPhaseGateManager $phaseGateManager) {}

  /**
   * @return array<string, array<string, mixed>>
   */
  public function present(int $projectNid): array {
    $result = [];
    foreach (self::LABELS as $gate => $label) {
      $decision = $this->phaseGateManager->evaluate($projectNid, $gate);
      $findings = array_map(static fn(array $finding): array => [
        'id' => (int) $finding['id'],
        'control_code' => (string) $finding['control_code'],
        'severity' => (string) $finding['severity'],
        'title' => (string) $finding['title'],
        'cause' => (string) $finding['cause'],
        'consequence' => (string) $finding['consequence'],
        'control_measure' => (string) $finding['control_measure'],
        'status' => (string) $finding['status'],
      ], $decision['blocking_findings']);

      $result[$gate] = [
        'label' => $label,
        'signal' => $decision['released'] ? 'GO' : 'STOP',
        'released' => (bool) $decision['released'],
        'blocking_count' => count($findings),
        'blocking_findings' => $findings,
        'exception' => $decision['exception'],
        'policy' => $decision['policy'],
        'ai_override_allowed' => FALSE,
        'evaluated_at' => $decision['evaluated_at'],
      ];
    }
    return $result;
  }

}
