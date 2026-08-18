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
      'controller_briefing' => $this->briefingBuilder->build($projectNid),
    ];
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
