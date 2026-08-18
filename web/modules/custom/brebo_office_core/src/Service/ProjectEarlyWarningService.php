<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\node\NodeInterface;

/**
 * Converts project control data into an explainable early-warning score.
 */
final class ProjectEarlyWarningService {

  public function __construct(
    private readonly ProjectFinancialControl $financialControl,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function analyze(NodeInterface $project): array {
    $finance = $this->financialControl->analyze($project);
    $score = 0;
    $drivers = [];

    $budgetCost = max(0.0, (float) $finance['budget_cost']);
    $variance = (float) $finance['variance'];
    if ($budgetCost > 0 && $variance > 0) {
      $pct = ($variance / $budgetCost) * 100;
      $points = min(30, (int) ceil($pct * 3));
      $score += $points;
      $drivers[] = $this->driver('cost_forecast', $points, 'Eindkostenprognose ligt boven werkbegroting.', $variance);
    }

    $marginDelta = (float) $finance['margin_delta_pct'];
    if ($marginDelta < 0) {
      $points = min(25, (int) ceil(abs($marginDelta) * 5));
      $score += $points;
      $drivers[] = $this->driver('margin_leakage', $points, 'Verwachte marge ligt onder de startmarge.', $marginDelta);
    }

    if ((float) $finance['expected_result'] < 0) {
      $points = 25;
      $score += $points;
      $drivers[] = $this->driver('expected_loss', $points, 'Project stuurt op een verwacht verlies.', (float) $finance['expected_result']);
    }

    $blocked = (int) $finance['blocked_invoices'];
    if ($blocked > 0) {
      $points = min(15, $blocked * 5);
      $score += $points;
      $drivers[] = $this->driver('blocked_invoices', $points, 'Leveranciersfacturen hebben geen geldige 3-way match.', $blocked);
    }

    $overdue = (int) $finance['overdue_invoices'];
    if ($overdue > 0) {
      $points = min(10, $overdue * 2);
      $score += $points;
      $drivers[] = $this->driver('overdue_payables', $points, 'Goedgekeurde leveranciersfacturen zijn vervallen en nog open.', $overdue);
    }

    $pendingVariation = abs((float) $finance['pending_variations']);
    $forecastRevenue = max(0.0, (float) $finance['forecast_revenue']);
    if ($pendingVariation > 0) {
      $exposurePct = $forecastRevenue > 0 ? ($pendingVariation / $forecastRevenue) * 100 : 100.0;
      $points = min(15, max(3, (int) ceil($exposurePct)));
      $score += $points;
      $drivers[] = $this->driver('uncontracted_variations', $points, 'Omzetprognose bevat nog niet gecontracteerd meer-/minderwerk.', $pendingVariation);
    }

    $budgetHours = max(0.0, (float) $finance['budget_hours']);
    $forecastHours = max(0.0, (float) $finance['forecast_hours']);
    if ($budgetHours > 0 && $forecastHours > $budgetHours) {
      $hoursPct = (($forecastHours - $budgetHours) / $budgetHours) * 100;
      $points = min(20, (int) ceil($hoursPct * 2));
      $score += $points;
      $drivers[] = $this->driver('labor_productivity', $points, 'Urenprognose overschrijdt het urenbudget.', $forecastHours - $budgetHours);
    }

    $score = min(100, $score);
    usort($drivers, static fn(array $a, array $b): int => $b['points'] <=> $a['points']);

    $level = match (TRUE) {
      $score >= 75 => 'kritiek',
      $score >= 50 => 'hoog',
      $score >= 25 => 'verhoogd',
      default => 'laag',
    };

    return [
      'score' => $score,
      'level' => $level,
      'status' => $score >= 50 ? 'Direct ingrijpen' : ($score >= 25 ? 'Actie vereist' : 'Onder controle'),
      'top_driver' => $drivers[0] ?? NULL,
      'drivers' => $drivers,
      'financial_snapshot' => [
        'forecast_cost' => $finance['forecast_cost'],
        'forecast_revenue' => $finance['forecast_revenue'],
        'expected_result' => $finance['expected_result'],
        'expected_margin_pct' => $finance['expected_margin_pct'],
        'margin_delta_pct' => $finance['margin_delta_pct'],
      ],
      'signals' => $finance['signals'],
    ];
  }

  /** @return array<string, mixed> */
  private function driver(string $code, int $points, string $message, float|int $value): array {
    return [
      'code' => $code,
      'points' => $points,
      'message' => $message,
      'value' => $value,
    ];
  }

}
