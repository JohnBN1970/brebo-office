<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/**
 * Controls contract revenue, variations and expected project margin.
 */
final class ProjectRevenueService {

  public function __construct(private readonly Connection $database) {}

  /**
   * @return array<string, mixed>
   */
  public function analyze(int $projectId, float $forecastCost): array {
    $rows = $this->database->select('brebo_project_revenue', 'r')
      ->fields('r')
      ->condition('project_nid', $projectId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $contract = 0.0;
    $approvedVariations = 0.0;
    $expectedVariations = 0.0;
    $pendingVariations = 0.0;
    $signals = [];

    foreach ($rows as &$row) {
      $amount = (float) $row['amount'];
      $type = (string) $row['revenue_type'];
      $status = (string) $row['status'];
      $probability = max(0.0, min(100.0, (float) $row['probability_pct']));
      $weighted = round($amount * ($probability / 100), 2);
      $row['weighted_amount'] = $weighted;

      if ($type === 'contract' && in_array($status, ['approved', 'contracted'], TRUE)) {
        $contract += $amount;
        continue;
      }
      if (!in_array($type, ['more_work', 'less_work'], TRUE)) {
        continue;
      }
      $signed = $type === 'less_work' ? -abs($amount) : abs($amount);
      if (in_array($status, ['approved', 'contracted'], TRUE)) {
        $approvedVariations += $signed;
        $expectedVariations += $signed;
      }
      elseif (in_array($status, ['submitted', 'pending'], TRUE)) {
        $pendingVariations += $signed;
        $expectedVariations += ($type === 'less_work' ? -abs($weighted) : abs($weighted));
      }
    }
    unset($row);

    $contractRevenue = $contract + $approvedVariations;
    $forecastRevenue = $contract + $expectedVariations;
    $expectedResult = $forecastRevenue - max(0.0, $forecastCost);
    $marginPct = $forecastRevenue > 0 ? ($expectedResult / $forecastRevenue) * 100 : 0.0;

    if ($contract <= 0) {
      $signals[] = 'Geen goedgekeurde aanneemsom/contractwaarde geregistreerd; projectmarge kan niet betrouwbaar worden beoordeeld.';
    }
    if ($expectedResult < -0.01) {
      $signals[] = 'Negatief verwacht projectresultaat: € ' . number_format(abs($expectedResult), 2, ',', '.') . ' verlies.';
    }
    if (abs($pendingVariations) > 0.01) {
      $signals[] = 'Openstaand meer-/minderwerk beïnvloedt de omzetprognose en is nog niet definitief gecontracteerd.';
    }

    return [
      'contract_value' => round($contract, 2),
      'approved_variations' => round($approvedVariations, 2),
      'pending_variations' => round($pendingVariations, 2),
      'contract_revenue' => round($contractRevenue, 2),
      'forecast_revenue' => round($forecastRevenue, 2),
      'forecast_cost' => round($forecastCost, 2),
      'expected_result' => round($expectedResult, 2),
      'expected_margin_pct' => round($marginPct, 2),
      'signals' => $signals,
      'rows' => $rows,
    ];
  }

}
