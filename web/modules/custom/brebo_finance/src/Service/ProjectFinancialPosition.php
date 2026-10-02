<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\ProjectFinancialPositionRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

/** Builds an auditable point-in-time financial project forecast. */
final class ProjectFinancialPosition {

  public function __construct(
    private readonly ProjectFinancialPositionRepositoryInterface $repository,
    private readonly VatCalculator $decimal,
  ) {}

  /** Creates one immutable daily forecast snapshot. */
  public function snapshot(
    int $projectNid,
    string $forecastRemainingCostExVat,
    string $riskReserveExVat,
    int $userId,
    ?string $snapshotDate = NULL,
  ): int {
    foreach (['forecastRemainingCostExVat' => $forecastRemainingCostExVat, 'riskReserveExVat' => $riskReserveExVat] as $field => $value) {
      if ($this->decimal->compare($value, '0') < 0) throw new InvalidArgumentException("$field may not be negative.");
    }

    $date = $snapshotDate ?? date('Y-m-d');
    $sourceStateBefore = $this->repository->sourceStateHash($projectNid);
    $values = $this->repository->values($projectNid);
    $contractRevenue = $values['contract_revenue'];
    $revenueMutations = $values['revenue_mutations'];
    $baselineCost = $values['baseline_cost'];
    $budgetMutations = $values['budget_mutations'];
    $committed = $values['committed'];
    $verifiedPerformance = $values['verified_performance'];
    $invoiced = $values['invoiced'];
    $paidIncVat = $values['paid_inc_vat'];

    $currentRevenue = $this->decimal->add($contractRevenue, $revenueMutations);
    $currentBudget = $this->decimal->add($baselineCost, $budgetMutations);
    $forecastEndCost = $this->decimal->add($this->decimal->add($committed, $forecastRemainingCostExVat), $riskReserveExVat);
    $forecastResult = $this->decimal->subtract($currentRevenue, $forecastEndCost);
    $forecastMargin = $this->decimal->percentage($forecastResult, $currentRevenue);

    // Verify that the exact source rows used by closure did not change while
    // this forecast was being calculated. This removes timestamp-ordering
    // ambiguity, including same-second writes.
    $sourceStateAfter = $this->repository->sourceStateHash($projectNid);
    if (!hash_equals($sourceStateBefore, $sourceStateAfter)) {
      throw new RuntimeException('Financiële brongegevens wijzigden tijdens het maken van de forecast. Probeer opnieuw.');
    }

    $fields = [
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
    $payload = $fields + ['source_state_hash' => $sourceStateAfter];
    $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

    return $this->repository->createSnapshot($fields + [
      'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
      'content_hash' => $hash,
      'created' => time(),
      'created_by' => $userId,
    ]);
  }

}
