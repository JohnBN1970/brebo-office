<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds the read-only financial project cockpit from verified source records.
 */
final class FinancialCockpitBuilder {

  public function __construct(
    private readonly Connection $database,
    private readonly ControllerBriefingBuilder $briefingBuilder,
    private readonly LabourProductivityManager $labourProductivityManager,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function build(int $projectNid): array {
    $forecast = $this->latestForecast($projectNid);
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
      'procurement_pipeline' => [
        'committed_ex_vat' => $this->sumExceptStatus(
          'brebo_finance_commitment',
          'amount_ex_vat',
          $projectNid,
          ['cancelled'],
        ),
        'verified_performance_ex_vat' => $this->sumByStatus(
          'brebo_finance_performance_receipt',
          'amount_ex_vat',
          $projectNid,
          ['verified'],
        ),
        'invoiced_ex_vat' => $this->sumExceptStatus(
          'brebo_finance_purchase_invoice',
          'amount_ex_vat',
          $projectNid,
          ['cancelled'],
        ),
        'paid_inc_vat' => $this->sumByStatus(
          'brebo_finance_payment_release',
          'total_amount',
          $projectNid,
          ['executed'],
        ),
      ],
      'workflow' => [
        'invoice_match_exceptions' => $this->countByStatus(
          'brebo_finance_purchase_invoice',
          $projectNid,
          ['exception'],
          'match_status',
        ),
        'payment_releases_pending' => $this->countExceptStatus(
          'brebo_finance_payment_release',
          $projectNid,
          ['executed', 'rejected', 'cancelled'],
        ),
        'budget_mutations_pending' => $this->countByStatus(
          'brebo_finance_budget_mutation',
          $projectNid,
          ['draft', 'in_review'],
        ),
        'ai_assessments_pending_review' => $this->countByStatus(
          'brebo_finance_ai_assessment',
          $projectNid,
          ['pending_review'],
        ),
        'control_resolutions_pending_verification' => $this->countByStatus(
          'brebo_finance_control_finding',
          $projectNid,
          ['pending_verification'],
        ),
      ],
      'g_account' => [
        'approved_instructions' => $this->countByStatus(
          'brebo_finance_g_account_instruction',
          $projectNid,
          ['approved'],
        ),
        'executed_amount' => $this->sumByStatus(
          'brebo_finance_g_account_payment',
          'amount',
          $projectNid,
          ['executed'],
        ),
      ],
      'labour_productivity' => $this->labourProductivityManager->analyzeProject($projectNid),
      'contract_obligations' => [
        'open_count' => $this->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['open', 'pending_verification', 'waiver_review'],
        ),
        'pending_verification' => $this->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['pending_verification'],
        ),
        'waiver_review' => $this->countByStatus(
          'brebo_finance_contract_obligation',
          $projectNid,
          ['waiver_review'],
        ),
        'open_exposure_ex_vat' => $this->sumByStatus(
          'brebo_finance_contract_obligation',
          'financial_exposure_ex_vat',
          $projectNid,
          ['open', 'pending_verification', 'waiver_review'],
        ),
      ],
      'supplier_scorecards' => $this->latestSupplierScores($projectNid),
      'failure_costs' => [
        'open_count' => $this->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['observed', 'validated', 'recovery_pending'],
        ),
        'awaiting_validation' => $this->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['observed'],
        ),
        'recovery_pending' => $this->countByStatus(
          'brebo_finance_failure_cost',
          $projectNid,
          ['recovery_pending'],
        ),
        'total_cost_ex_vat' => $this->sumByStatus(
          'brebo_finance_failure_cost',
          'total_cost_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
        'recovered_ex_vat' => $this->sumByStatus(
          'brebo_finance_failure_cost',
          'recovered_amount_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
        'net_failure_cost_ex_vat' => $this->sumByStatus(
          'brebo_finance_failure_cost',
          'net_failure_cost_ex_vat',
          $projectNid,
          ['observed', 'validated', 'recovery_pending', 'closed'],
        ),
      ],
      'change_orders' => [
        'open_count' => $this->countExceptStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['client_rejected', 'paid'],
        ),
        'awaiting_client_decision' => $this->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['offered'],
        ),
        'execution_at_risk' => $this->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['risk_review', 'risk_accepted'],
        ),
        'executed_not_invoiced' => $this->countByStatus(
          'brebo_finance_change_order',
          $projectNid,
          ['executed'],
        ),
        'open_sales_ex_vat' => $this->sumByStatus(
          'brebo_finance_change_order',
          'sales_amount_ex_vat',
          $projectNid,
          ['priced', 'offered', 'client_approved', 'risk_review', 'risk_accepted', 'executed', 'invoiced'],
        ),
        'open_margin_impact_ex_vat' => $this->sumByStatus(
          'brebo_finance_change_order',
          'margin_amount_ex_vat',
          $projectNid,
          ['priced', 'offered', 'client_approved', 'risk_review', 'risk_accepted', 'executed', 'invoiced'],
        ),
      ],
      'cash_forecast' => [
        'committed' => $this->latestCashForecast($projectNid, 'committed'),
        'expected' => $this->latestCashForecast($projectNid, 'expected'),
      ],
      'controller_briefing' => $this->briefingBuilder->build($projectNid),
    ];
  }



  /**
   * @return list<array<string, mixed>>
   */
  private function latestSupplierScores(int $projectNid): array {
    $rows = $this->database->select('brebo_finance_supplier_score_snapshot', 's')
      ->fields('s', [
        'id',
        'supplier_ref',
        'supplier_name',
        'snapshot_date',
        'policy_version',
        'weighted_score',
        'confidence_class',
        'delivery_score',
        'quality_score',
        'invoice_score',
        'price_score',
        'failure_cost_score',
        'order_count',
        'receipt_count',
        'invoice_count',
        'content_hash',
      ])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $latest = [];
    foreach ($rows as $row) {
      $supplier = (string) $row['supplier_ref'];
      if (!isset($latest[$supplier])) {
        $latest[$supplier] = $row;
      }
    }
    return array_values($latest);
  }

  /**
   * @return array<string, mixed>|null
   */
  private function latestCashForecast(int $projectNid, string $scenario): ?array {
    $record = $this->database->select('brebo_finance_cash_forecast_snapshot', 's')
      ->fields('s', [
        'id',
        'snapshot_date',
        'scenario',
        'opening_regular_balance',
        'opening_g_account_balance',
        'lowest_regular_balance',
        'lowest_g_account_balance',
        'first_regular_shortfall_date',
        'first_g_account_shortfall_date',
        'content_hash',
        'created',
        'created_by',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('scenario', $scenario)
      ->orderBy('snapshot_date', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record !== FALSE ? $record : NULL;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function latestForecast(int $projectNid): ?array {
    $record = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f', [
        'id',
        'snapshot_date',
        'current_revenue_ex_vat',
        'current_budget_ex_vat',
        'committed_ex_vat',
        'verified_performance_ex_vat',
        'invoiced_ex_vat',
        'paid_inc_vat',
        'forecast_remaining_cost_ex_vat',
        'risk_reserve_ex_vat',
        'forecast_end_cost_ex_vat',
        'forecast_result_ex_vat',
        'forecast_margin_pct',
        'content_hash',
        'created',
        'created_by',
      ])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record !== FALSE ? $record : NULL;
  }

  /**
   * @param list<string> $statuses
   */
  private function sumByStatus(
    string $table,
    string $field,
    int $projectNid,
    array $statuses,
  ): string {
    $query = $this->database->select($table, 't');
    $query->condition('project_nid', $projectNid);
    $query->condition('status', $statuses, 'IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  /**
   * @param list<string> $excludedStatuses
   */
  private function sumExceptStatus(
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
   * @param list<string> $statuses
   */
  private function countByStatus(
    string $table,
    int $projectNid,
    array $statuses,
    string $statusField = 'status',
  ): int {
    return (int) $this->database->select($table, 't')
      ->condition('project_nid', $projectNid)
      ->condition($statusField, $statuses, 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * @param list<string> $excludedStatuses
   */
  private function countExceptStatus(
    string $table,
    int $projectNid,
    array $excludedStatuses,
  ): int {
    return (int) $this->database->select($table, 't')
      ->condition('project_nid', $projectNid)
      ->condition('status', $excludedStatuses, 'NOT IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
