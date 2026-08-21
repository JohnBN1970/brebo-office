<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Controls approved budget changes and forecast snapshots. */
final class WorkBudgetControlService {
  public function __construct(private readonly Connection $database) {}

  public function addChange(int $workBudgetId, ?int $lineId, string $changeType, float $amountDelta, string $reason, ?string $sourceReference, int $uid): int {
    $status = $this->database->select('brebo_work_budget', 'b')->fields('b', ['status'])->condition('id', $workBudgetId)->execute()->fetchField();
    if ($status !== 'approved') {
      throw new \LogicException('Budgetwijzigingen kunnen alleen op een goedgekeurde werkbegroting worden vastgelegd.');
    }
    return (int) $this->database->insert('brebo_work_budget_change')->fields([
      'work_budget_id' => $workBudgetId,
      'work_budget_line_id' => $lineId,
      'change_type' => $changeType,
      'status' => 'draft',
      'amount_delta' => $amountDelta,
      'reason' => trim($reason),
      'source_reference' => $sourceReference,
      'created' => time(),
      'created_by' => $uid,
    ])->execute();
  }

  public function approveChange(int $changeId, int $uid): void {
    $change = $this->database->select('brebo_work_budget_change', 'c')->fields('c')->condition('id', $changeId)->execute()->fetchAssoc();
    if (!$change) { throw new \InvalidArgumentException('Budgetwijziging bestaat niet.'); }
    if ($change['status'] === 'approved') { return; }
    if ($change['status'] !== 'draft') { throw new \LogicException('Alleen concept-budgetwijzigingen kunnen worden goedgekeurd.'); }
    if (trim((string) $change['reason']) === '') { throw new \LogicException('Een budgetwijziging vereist een reden.'); }
    $this->database->update('brebo_work_budget_change')->fields([
      'status' => 'approved',
      'approved' => time(),
      'approved_by' => $uid,
    ])->condition('id', $changeId)->execute();
  }

  /**
   * @return array{baseline:float,approved_changes:float,current_budget:float,committed:float,actual:float,forecast_final_cost:float,forecast_result:float}
   */
  public function totals(int $workBudgetId, float $committed = 0.0, float $actual = 0.0, ?float $forecastFinalCost = NULL): array {
    $q = $this->database->select('brebo_work_budget_line', 'l');
    $q->addExpression('COALESCE(SUM(l.budget_amount), 0)', 'total');
    $q->condition('work_budget_id', $workBudgetId);
    $baseline = (float) $q->execute()->fetchField();

    $c = $this->database->select('brebo_work_budget_change', 'c');
    $c->addExpression('COALESCE(SUM(c.amount_delta), 0)', 'total');
    $c->condition('work_budget_id', $workBudgetId)->condition('status', 'approved');
    $changes = (float) $c->execute()->fetchField();

    $current = $baseline + $changes;
    $forecast = $forecastFinalCost ?? max($actual, $committed, $current);
    return [
      'baseline' => $baseline,
      'approved_changes' => $changes,
      'current_budget' => $current,
      'committed' => $committed,
      'actual' => $actual,
      'forecast_final_cost' => $forecast,
      'forecast_result' => $current - $forecast,
    ];
  }

  public function snapshotForecast(int $workBudgetId, float $committed, float $actual, ?float $forecastFinalCost, int $uid, ?string $date = NULL): int {
    $totals = $this->totals($workBudgetId, $committed, $actual, $forecastFinalCost);
    return (int) $this->database->insert('brebo_work_budget_forecast')->fields([
      'work_budget_id' => $workBudgetId,
      'forecast_date' => $date ?? date('Y-m-d'),
      'baseline_amount' => $totals['baseline'],
      'approved_changes_amount' => $totals['approved_changes'],
      'current_budget_amount' => $totals['current_budget'],
      'committed_amount' => $totals['committed'],
      'actual_amount' => $totals['actual'],
      'forecast_final_cost' => $totals['forecast_final_cost'],
      'forecast_result' => $totals['forecast_result'],
      'created' => time(),
      'created_by' => $uid,
    ])->execute();
  }
}
