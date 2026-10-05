<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialCockpitReadRepositoryInterface;

/**
 * Builds the read-only financial project cockpit from verified source records.
 */
final class FinancialCockpitBuilder {

  public function __construct(
    private readonly FinancialCockpitReadRepositoryInterface $repository,
    private readonly ControllerBriefingBuilder $briefingBuilder,
    private readonly LabourProductivityManager $labourProductivityManager,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function build(int $projectNid): array {
    $forecast = $this->repository->latestForecast($projectNid);
    $snapshotDate = $forecast['snapshot_date'] ?? NULL;
    $ageDays = $snapshotDate !== NULL
      ? max(0, (int) floor((time() - strtotime((string) $snapshotDate)) / 86400))
      : NULL;

    return [
      'project_nid' => $projectNid,
      'generated_at' => time(),
      'basis' => [
        'amounts_ex_vat' => 'Result, budget, commitments, performance and invoices.',
        'amounts_inc_vat' => 'Payments and cash position.',
        'financial_source' => 'Moneybird',
        'operational_control_source' => 'BREBO Office',
      ],
      'forecast' => $forecast,
      'forecast_age_days' => $ageDays,
      'forecast_is_stale' => $ageDays === NULL || $ageDays > 30,
      'financial_scenarios' => $this->repository->latestScenarioSnapshots($projectNid),
      'cost_intelligence' => [
        'verified_observation_count' => $this->repository->verifiedCostObservationCount($projectNid),
        'observed_cost_codes' => $this->repository->observedCostCodes($projectNid),
        'latest_exact_benchmarks' => $this->repository->latestCostBenchmarks($projectNid),
        'basis' => 'Exact specification, unit and region; historical values are not silently indexed.',
      ],
      'billing_position' => [
        'planned_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_billing_instalment',
          'amount_ex_vat',
          $projectNid,
          ['planned'],
        ),
        'billable_not_invoiced_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_billing_instalment',
          'amount_ex_vat',
          $projectNid,
          ['billable'],
        ),
        'invoiced_ex_vat' => $this->repository->sumExceptStatus(
          'brebo_finance_sales_invoice',
          'amount_ex_vat',
          $projectNid,
          ['draft', 'cancelled'],
        ),
        'invoiced_inc_vat' => $this->repository->sumExceptStatus(
          'brebo_finance_sales_invoice',
          'amount_inc_vat',
          $projectNid,
          ['draft', 'cancelled'],
        ),
        'paid_inc_vat' => $this->repository->sumExceptStatus(
          'brebo_finance_sales_invoice',
          'paid_amount_inc_vat',
          $projectNid,
          ['draft', 'cancelled'],
        ),
        'overdue_count' => $this->repository->countByStatus(
          'brebo_finance_sales_invoice',
          $projectNid,
          ['overdue'],
        ),
        'disputed_count' => $this->repository->countByStatus(
          'brebo_finance_sales_invoice',
          $projectNid,
          ['disputed'],
        ),
      ],
      'procurement_pipeline' => [
        'committed_ex_vat' => $this->repository->sumExceptStatus(
          'brebo_finance_commitment',
          'amount_ex_vat',
          $projectNid,
          ['cancelled'],
        ),
        'verified_performance_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_performance_receipt',
          'amount_ex_vat',
          $projectNid,
          ['verified'],
        ),
        'invoiced_ex_vat' => $this->repository->sumExceptStatus(
          'brebo_finance_purchase_invoice',
          'amount_ex_vat',
          $projectNid,
          ['cancelled'],
        ),
        'paid_inc_vat' => $this->repository->sumByStatus(
          'brebo_finance_payment_release',
          'total_amount',
          $projectNid,
          ['executed'],
        ),
      ],
      'workflow' => [
        'invoice_match_exceptions' => $this->repository->countByStatus(
          'brebo_finance_purchase_invoice',
          $projectNid,
          ['exception'],
          'match_status',
        ),
        'payment_releases_pending' => $this->repository->countExceptStatus(
          'brebo_finance_payment_release',
          $projectNid,
          ['executed', 'rejected', 'cancelled'],
        ),
        'budget_mutations_pending' => $this->repository->countByStatus(
          'brebo_finance_budget_mutation',
          $projectNid,
          ['draft', 'in_review'],
        ),
        'ai_assessments_pending_review' => $this->repository->countByStatus(
          'brebo_finance_ai_assessment',
          $projectNid,
          ['pending_review'],
        ),
        'control_resolutions_pending_verification' => $this->repository->countByStatus(
          'brebo_finance_control_finding',
          $projectNid,
          ['pending_verification'],
        ),
      ],
      'g_account' => [
        'approved_instructions' => $this->repository->countByStatus(
          'brebo_finance_g_account_instruction',
          $projectNid,
          ['approved'],
        ),
        'executed_amount' => $this->repository->sumByStatus(
          'brebo_finance_g_account_payment',
          'amount',
          $projectNid,
          ['executed'],
        ),
      ],
      'labour_productivity' => $this->labourProductivityManager->analyzeProject($projectNid),
      'contract_obligations' => [
        'open_count' => $this->repository->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['open', 'pending_verification', 'waiver_review'],
        ),
        'pending_verification' => $this->repository->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['pending_verification'],
        ),
        'waiver_review' => $this->repository->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['waiver_review'],
        ),
        'open_exposure_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_contract_obligation',
          'financial_exposure_ex_vat',
          $projectNid,
          ['open', 'pending_verification', 'waiver_review'],
        ),
      ],
      'supplier_scorecards' => $this->repository->latestSupplierScores($projectNid),
      'failure_costs' => [
        'open_count' => $this->repository->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['observed', 'validated', 'recovery_pending'],
        ),
        'awaiting_validation' => $this->repository->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['observed'],
        ),
        'recovery_pending' => $this->repository->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['recovery_pending'],
        ),
        'total_cost_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_failure_cost',
          'total_cost_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
        'recovered_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_failure_cost',
          'recovered_amount_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
        'net_failure_cost_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_failure_cost',
          'net_failure_cost_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
      ],
      'change_orders' => [
        'open_count' => $this->repository->countExceptStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['client_rejected', 'paid'],
        ),
        'awaiting_client_decision' => $this->repository->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['offered'],
        ),
        'execution_at_risk' => $this->repository->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['risk_review', 'risk_accepted'],
        ),
        'executed_not_invoiced' => $this->repository->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['executed'],
        ),
        'open_sales_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_change_order',
          'sales_amount_ex_vat',
          $projectNid,
          ['priced', 'offered', 'client_approved', 'risk_review', 'risk_accepted', 'executed', 'invoiced'],
        ),
        'open_margin_impact_ex_vat' => $this->repository->sumByStatus(
          'brebo_finance_change_order',
          'margin_amount_ex_vat',
          $projectNid,
          ['priced', 'offered', 'client_approved', 'risk_review', 'risk_accepted', 'executed', 'invoiced'],
        ),
      ],
      'cash_forecast' => [
        'committed' => $this->repository->latestCashForecast($projectNid, 'committed'),
        'expected' => $this->repository->latestCashForecast($projectNid, 'expected'),
      ],
      'controller_briefing' => $this->briefingBuilder->build($projectNid),
    ];
  }




}
