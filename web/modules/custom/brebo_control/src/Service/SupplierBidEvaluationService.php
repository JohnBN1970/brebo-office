<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

/**
 * Compares supplier bids on expected economic cost instead of price alone.
 */
final class SupplierBidEvaluationService {

  public function __construct(private readonly SupplierScorecardService $scorecards) {}

  /**
   * @param array<int, array<string, mixed>> $bids
   *   Each bid requires supplier_name and bid_amount.
   *
   * @return array<string, mixed>
   */
  public function compare(array $bids): array {
    $results = [];
    foreach ($bids as $bid) {
      $supplier = trim((string) ($bid['supplier_name'] ?? ''));
      $amount = (float) ($bid['bid_amount'] ?? 0);
      if ($supplier === '' || $amount <= 0) {
        throw new \InvalidArgumentException('Iedere aanbieding vereist supplier_name en een positieve bid_amount.');
      }

      $card = $this->scorecards->supplier($supplier);
      $riskPct = $this->expectedRiskPct($card);
      $expectedRiskCost = $amount * ($riskPct / 100);
      $economicCost = $amount + $expectedRiskCost;

      $results[] = [
        'supplier_name' => $supplier,
        'bid_amount' => round($amount, 2),
        'supplier_score' => $card['tco_adjusted_score'] ?? NULL,
        'supplier_rating' => $card['rating'] ?? NULL,
        'confidence' => $card['confidence'] ?? 'onvoldoende',
        'historic_failure_cost' => round((float) ($card['failure_cost'] ?? 0), 2),
        'historic_failure_cost_pct' => round((float) ($card['failure_cost_pct_turnover'] ?? 0), 2),
        'expected_risk_pct' => round($riskPct, 2),
        'expected_risk_cost' => round($expectedRiskCost, 2),
        'economic_cost' => round($economicCost, 2),
        'evidence_status' => $card === NULL ? 'geen_historie' : 'historie_beschikbaar',
      ];
    }

    usort($results, static fn(array $a, array $b): int => $a['economic_cost'] <=> $b['economic_cost']);
    foreach ($results as $index => &$row) {
      $row['economic_rank'] = $index + 1;
      $row['recommended'] = $index === 0;
      $row['saving_vs_highest_economic_cost'] = 0.0;
    }
    unset($row);

    if ($results !== []) {
      $highest = max(array_column($results, 'economic_cost'));
      foreach ($results as &$row) {
        $row['saving_vs_highest_economic_cost'] = round($highest - (float) $row['economic_cost'], 2);
      }
      unset($row);
    }

    return [
      'recommendation' => $results[0] ?? NULL,
      'bids' => $results,
      'method' => 'aanbiedingsprijs + evidence-based verwacht leveranciersrisico',
      'warning' => 'Geen leveranciershistorie betekent geen verzonnen risicopremie; beoordeel ontbrekend bewijs expliciet vóór opdracht.',
    ];
  }

  /** @param array<string, mixed>|null $card */
  private function expectedRiskPct(?array $card): float {
    if ($card === NULL) {
      return 0.0;
    }

    $failurePct = max(0.0, (float) ($card['failure_cost_pct_turnover'] ?? 0));
    $score = max(0, min(100, (int) ($card['tco_adjusted_score'] ?? 100)));
    $scoreRisk = (100 - $score) * 0.08;
    $confidenceFactor = match ((string) ($card['confidence'] ?? 'onvoldoende')) {
      'hoog' => 1.0,
      'middel' => 0.75,
      'laag' => 0.5,
      default => 0.25,
    };

    return min(25.0, ($failurePct + $scoreRisk) * $confidenceFactor);
  }

}
