<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Turns recurring root causes and proven controls into governed process improvements. */
final class ContinuousImprovementEngine {

  public function __construct(
    private readonly RootCauseIntelligenceService $rootCauses,
    private readonly ControlEffectivenessIntelligenceService $effectiveness,
  ) {}

  /** @return array<string, mixed> */
  public function propose(): array {
    $root = $this->rootCauses->analyze();
    $effective = $this->effectiveness->analyze();
    $controls = [];
    foreach ((array) ($effective['controls'] ?? []) as $control) {
      if (($control['classification'] ?? '') === 'effective' && (float) ($control['verified_effectiveness_pct'] ?? 0) >= 80) {
        $controls[] = $control;
      }
    }

    $proposals = [];
    foreach ((array) ($root['hypotheses'] ?? []) as $cause) {
      if (!($cause['review_warranted'] ?? FALSE)) {
        continue;
      }
      $best = $controls[0] ?? NULL;
      $proposals[] = [
        'root_cause_code' => (string) ($cause['code'] ?? 'unknown'),
        'root_cause' => (string) ($cause['label'] ?? 'Onbekende oorzaak'),
        'evidence_score' => (int) ($cause['score'] ?? 0),
        'confidence' => (string) ($cause['confidence'] ?? 'laag'),
        'recommended_control' => $best['action_key'] ?? NULL,
        'control_effectiveness_pct' => $best['verified_effectiveness_pct'] ?? NULL,
        'proposal' => $this->proposalFor((string) ($cause['code'] ?? ''), $best),
        'governance_status' => 'human_approval_required',
      ];
    }

    return [
      'proposal_count' => count($proposals),
      'proposals' => $proposals,
      'governance' => 'Proceswijzigingen worden uitsluitend voorgesteld. Invoering vereist eigenaar, impactbeoordeling, menselijke goedkeuring en herhaalde effectmeting.',
    ];
  }

  /** @param array<string, mixed>|null $control */
  private function proposalFor(string $cause, ?array $control): string {
    $base = match ($cause) {
      'process_follow_up' => 'Maak de controle een verplichte workflowpoort met eigenaar en escalatiedeadline.',
      'supplier_performance' => 'Verhoog leverancierscontrole en koppel nieuwe opdrachten aan aantoonbare prestatieverbetering.',
      'supplier_selection' => 'Verzwaar leveranciersselectie met TCO-, historie- en controlmodusreview.',
      'ownership_capacity' => 'Maak eigenaarschap expliciet en voeg automatische capaciteits- en deadline-escalatie toe.',
      'late_intervention' => 'Verplaats de controle eerder in het proces zodat blokkeren vóór financiële of operationele impact mogelijk is.',
      'control_design' => 'Herontwerp de beheersmaatregel en vervang zwakke controles door aantoonbaar effectievere poorten.',
      'data_context_quality' => 'Maak ontbrekende context en bewijsdata verplicht vóór besluitvorming of vrijgave.',
      default => 'Start een gerichte procesreview en valideer de vermoedelijke hoofdoorzaak met bronbewijs.',
    };
    if ($control !== NULL) {
      $base .= ' Gebruik waar passend bewezen control ' . ($control['action_key'] ?? '') . ' als referentie (' . round((float) ($control['verified_effectiveness_pct'] ?? 0), 1) . '% geverifieerde effectiviteit).';
    }
    return $base;
  }
}
