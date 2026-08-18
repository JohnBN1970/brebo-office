<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\brebo_control\Service\SupplierBidEvaluationService;
use Drupal\Core\Database\Connection;

/** Enforces explicit approval when procurement deviates from economic #1. */
final class ProcurementDecisionGate {

  public function __construct(
    private readonly Connection $database,
    private readonly SupplierBidEvaluationService $bidEvaluation,
  ) {}

  /** @param array<int, array<string, mixed>> $bids
   *  @return array<string, mixed>
   */
  public function decide(int $projectNid, string $procurementRef, array $bids, string $selectedSupplier, int $decidedBy, ?string $reason = NULL, ?string $impact = NULL, ?int $approvedBy = NULL, ?int $now = NULL): array {
    $now ??= time();
    $evaluation = $this->bidEvaluation->compare($bids);
    $recommended = $evaluation['recommendation'] ?? NULL;
    if (!is_array($recommended)) {
      throw new \InvalidArgumentException('Geen geldige aanbiedingen om te beoordelen.');
    }

    $selected = NULL;
    foreach ($evaluation['bids'] as $bid) {
      if (strcasecmp((string) $bid['supplier_name'], $selectedSupplier) === 0) {
        $selected = $bid;
        break;
      }
    }
    if ($selected === NULL) {
      throw new \InvalidArgumentException('Geselecteerde leverancier staat niet in de beoordeelde aanbiedingen.');
    }

    $isRecommended = strcasecmp((string) $selected['supplier_name'], (string) $recommended['supplier_name']) === 0;
    $delta = max(0.0, (float) $selected['economic_cost'] - (float) $recommended['economic_cost']);
    $status = 'approved_economic_best';

    if (!$isRecommended) {
      if (trim((string) $reason) === '' || trim((string) $impact) === '') {
        $status = 'blocked_missing_justification';
      }
      elseif ($approvedBy === NULL || $approvedBy <= 0) {
        $status = 'pending_approval';
      }
      else {
        $status = 'approved_deviation';
      }
    }

    $id = (int) $this->database->insert('brebo_procurement_decision')->fields([
      'project_nid' => $projectNid,
      'procurement_ref' => $procurementRef,
      'selected_supplier' => (string) $selected['supplier_name'],
      'recommended_supplier' => (string) $recommended['supplier_name'],
      'selected_economic_cost' => (float) $selected['economic_cost'],
      'recommended_economic_cost' => (float) $recommended['economic_cost'],
      'economic_delta' => round($delta, 2),
      'decision_status' => $status,
      'deviation_reason' => $reason,
      'deviation_impact' => $impact,
      'approved_by' => $status === 'approved_deviation' ? $approvedBy : NULL,
      'approved_at' => $status === 'approved_deviation' ? $now : NULL,
      'decided_by' => $decidedBy,
      'created' => $now,
    ])->execute();

    return [
      'decision_id' => $id,
      'allowed_to_award' => in_array($status, ['approved_economic_best', 'approved_deviation'], TRUE),
      'status' => $status,
      'selected' => $selected,
      'recommended' => $recommended,
      'economic_delta' => round($delta, 2),
      'message' => $this->message($status, $delta),
      'evaluation' => $evaluation,
    ];
  }

  private function message(string $status, float $delta): string {
    return match ($status) {
      'approved_economic_best' => 'Gekozen leverancier is economisch nummer 1. Opdracht mag worden verstrekt.',
      'approved_deviation' => 'Afwijking is gemotiveerd en goedgekeurd. Opdracht mag worden verstrekt.',
      'pending_approval' => 'Afwijking kost naar verwachting € ' . number_format($delta, 2, ',', '.') . ' extra en vereist bevoegde goedkeuring.',
      default => 'Opdracht geblokkeerd: motiveer waarom van economisch nummer 1 wordt afgeweken en leg de verwachte impact vast.',
    };
  }
}
