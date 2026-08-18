<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Builds an auditable point-in-time financial project forecast.
 */
final class ProjectFinancialPosition {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Creates one immutable daily forecast snapshot.
   *
   * Remaining cost means expected future cost not yet included in commitments.
   */
  public function snapshot(
    int $projectNid,
    string $forecastRemainingCostExVat,
    string $riskReserveExVat,
    int $userId,
    ?string $snapshotDate = NULL,
  ): int {
    foreach ([
      'forecastRemainingCostExVat' => $forecastRemainingCostExVat,
      'riskReserveExVat' => $riskReserveExVat,
    ] as $field => $value) {
      if ($this->decimal->compare($value, '0') < 0) {
        throw new InvalidArgumentException("$field may not be negative.");
      }
    }

    $date = $snapshotDate ?? date('Y-m-d');
    $contractRevenue = $this->singleValue(
      'brebo_finance_project_contract',
      'amount_ex_vat',
      ['project_nid' => $projectNid, 'status' => 'approved'],
      TRUE,
    );
    $revenueMutations = $this->sum(
      'brebo_finance_revenue_mutation',
      'amount_ex_vat',
      ['project_nid' => $projectNid, 'status' => 'approved'],
    );
    $baselineCost = $this->singleValue(
      'brebo_finance_budget',
      'id',
      ['project_nid' => $projectNid, 'budget_type' => 'working', 'status' => 'locked'],
      TRUE,
      fn (string $budgetId): string => $this->sum(
        'brebo_finance_budget_line',
        'amount_ex_vat',
        ['budget_id' => (int) $budgetId],
      ),
    );
    $budgetMutations = $this->sumJoinedApprovedBudgetMutations($projectNid);
    $committed = $this->sumWithExcludedStatus(
      'brebo_finance_commitment',
      'amount_ex_vat',
      $projectNid,
      ['cancelled'],
    );
    $verifiedPerformance = $this->sum(
      'brebo_finance_performance_receipt',
      'amount_ex_vat',
      ['project_nid' => $projectNid, 'status' => 'verified'],
    );
    $invoiced = $this->sumWithExcludedStatus(
      'brebo_finance_purchase_invoice',
      'amount_ex_vat',
      $projectNid,
      ['cancelled'],
    );
    $paidIncVat = $this->sum(
      'brebo_finance_payment_release',
      'total_amount',
      ['project_nid' => $projectNid, 'status' => 'executed'],
    );

    $currentRevenue = $this->decimal->add($contractRevenue, $revenueMutations);
    $currentBudget = $this->decimal->add($baselineCost, $budgetMutations);
    $forecastEndCost = $this->decimal->add(
      $this->decimal->add($committed, $forecastRemainingCostExVat),
      $riskReserveExVat,
    );
    $forecastResult = $this->decimal->subtract($currentRevenue, $forecastEndCost);
    $forecastMargin = $this->decimal->percentage($forecastResult, $currentRevenue);

    $payload = [
      'project_nid' => $projectNid,
      'snapshot_date' => $date,
      'contract_revenue_ex_vat' => $contractRevenue,
      'approved_revenue_mutations_ex_vat' => $revenueMutations,
      'current_revenue_ex_vat' => $currentRevenue,
      'baseline_cost_ex_vat' => $baselineCost,
      'approved_budget_mutations_ex_vat' => $budgetMutations,
      'current_budget_ex_vat' => $currentBudget,
      'committed_ex_vat' => $committed,
      'verified_performance_ex_vat' => $verifiedPerformance,
      'invoiced_ex_vat' => $invoiced,
      'paid_inc_vat' => $paidIncVat,
      'forecast_remaining_cost_ex_vat' => $forecastRemainingCostExVat,
      'risk_reserve_ex_vat' => $riskReserveExVat,
      'forecast_end_cost_ex_vat' => $forecastEndCost,
      'forecast_result_ex_vat' => $forecastResult,
      'forecast_margin_pct' => $forecastMargin,
    ];
    $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

    return (int) $this->database->insert('brebo_finance_forecast_snapshot')
      ->fields($payload + [
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'content_hash' => $hash,
        'created' => time(),
        'created_by' => $userId,
      ])
      ->execute();
  }

  /**
   * @param array<string, int|string> $conditions
   */
  private function sum(string $table, string $field, array $conditions): string {
    $query = $this->database->select($table, 't');
    foreach ($conditions as $name => $value) {
      $query->condition($name, $value);
    }
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  private function sumWithExcludedStatus(
    string $table,
    string $field,
    int $projectNid,
    array $excludedStatuses,
  ): string {
    $query = $this->database->select($table, 't');
    $query->condition('project_nid', $projectNid);
    $query->condition('status', $excludedStatuses, 'NOT IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  /**
   * Loads one required value and optionally transforms it.
   *
   * @param array<string, int|string> $conditions
   */
  private function singleValue(
    string $table,
    string $field,
    array $conditions,
    bool $required,
    ?callable $transform = NULL,
  ): string {
    $query = $this->database->select($table, 't')->fields('t', [$field]);
    foreach ($conditions as $name => $value) {
      $query->condition($name, $value);
    }
    $value = $query->range(0, 1)->execute()->fetchField();
    if ($value === FALSE) {
      if ($required) {
        throw new UnexpectedValueException("Required financial source $table is missing.");
      }
      return '0.0000';
    }
    return $transform !== NULL ? $transform((string) $value) : (string) $value;
  }

  private function sumJoinedApprovedBudgetMutations(int $projectNid): string {
    $query = $this->database->select('brebo_finance_budget_mutation', 'm');
    $query->condition('m.project_nid', $projectNid);
    $query->condition('m.status', 'approved');
    $query->addExpression('COALESCE(SUM(m.amount_ex_vat), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

}
