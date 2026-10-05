<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialCommandCenterRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for bounded organisation-wide Finance aggregates. */
final class DatabaseFinancialCommandCenterRepository implements FinancialCommandCenterRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function portfolio(array $projectIds): array {
    return [
      'project_count' => count($projectIds),
      'billable_not_invoiced_ex_vat' => $this->sumByStatus('brebo_finance_billing_instalment', 'amount_ex_vat', $projectIds, ['billable']),
      'invoiced_ex_vat' => $this->sumExceptStatus('brebo_finance_sales_invoice', 'amount_ex_vat', $projectIds, ['draft', 'cancelled']),
      'committed_ex_vat' => $this->sumExceptStatus('brebo_finance_commitment', 'amount_ex_vat', $projectIds, ['cancelled']),
      'open_contract_exposure_ex_vat' => $this->sumByStatus('brebo_finance_contract_obligation', 'financial_exposure_ex_vat', $projectIds, ['open', 'pending_verification', 'waiver_review']),
      'net_failure_cost_ex_vat' => $this->sumByStatus('brebo_finance_failure_cost', 'net_failure_cost_ex_vat', $projectIds, ['observed', 'validated', 'recovery_pending', 'closed']),
      'open_change_order_sales_ex_vat' => $this->sumByStatus('brebo_finance_change_order', 'sales_amount_ex_vat', $projectIds, ['priced', 'offered', 'client_approved', 'risk_review', 'risk_accepted', 'executed', 'invoiced']),
      'overdue_invoice_count' => $this->countByStatus('brebo_finance_sales_invoice', $projectIds, ['overdue']),
      'pending_payment_releases' => $this->countExceptStatus('brebo_finance_payment_release', $projectIds, ['executed', 'rejected', 'cancelled']),
      'forecast_stale_count' => $this->staleForecastCount($projectIds),
    ];
  }

  /** @param list<int> $projectIds @param list<string> $statuses */
  private function sumByStatus(string $table, string $field, array $projectIds, array $statuses): float {
    if ($projectIds === [] || !$this->tableHas($table, [$field, 'project_nid', 'status'])) return 0.0;
    $query = $this->database->select($table, 't');
    $query->addExpression('COALESCE(SUM(t.' . $field . '), 0)', 'total');
    return (float) $query->condition('project_nid', $projectIds, 'IN')->condition('status', $statuses, 'IN')->execute()->fetchField();
  }

  /** @param list<int> $projectIds @param list<string> $statuses */
  private function sumExceptStatus(string $table, string $field, array $projectIds, array $statuses): float {
    if ($projectIds === [] || !$this->tableHas($table, [$field, 'project_nid', 'status'])) return 0.0;
    $query = $this->database->select($table, 't');
    $query->addExpression('COALESCE(SUM(t.' . $field . '), 0)', 'total');
    return (float) $query->condition('project_nid', $projectIds, 'IN')->condition('status', $statuses, 'NOT IN')->execute()->fetchField();
  }

  /** @param list<int> $projectIds @param list<string> $statuses */
  private function countByStatus(string $table, array $projectIds, array $statuses): int {
    if ($projectIds === [] || !$this->tableHas($table, ['project_nid', 'status'])) return 0;
    return (int) $this->database->select($table, 't')->condition('project_nid', $projectIds, 'IN')->condition('status', $statuses, 'IN')->countQuery()->execute()->fetchField();
  }

  /** @param list<int> $projectIds @param list<string> $statuses */
  private function countExceptStatus(string $table, array $projectIds, array $statuses): int {
    if ($projectIds === [] || !$this->tableHas($table, ['project_nid', 'status'])) return 0;
    return (int) $this->database->select($table, 't')->condition('project_nid', $projectIds, 'IN')->condition('status', $statuses, 'NOT IN')->countQuery()->execute()->fetchField();
  }

  /** @param list<int> $projectIds */
  private function staleForecastCount(array $projectIds): int {
    if ($projectIds === [] || !$this->tableHas('brebo_finance_forecast_snapshot', ['project_nid', 'snapshot_date'])) return count($projectIds);
    $threshold = date('Y-m-d', strtotime('-30 days'));
    $query = $this->database->select('brebo_finance_forecast_snapshot', 'f');
    $query->addField('f', 'project_nid');
    $query->addExpression('MAX(f.snapshot_date)', 'latest_snapshot');
    $query->condition('project_nid', $projectIds, 'IN')->groupBy('project_nid');
    $fresh = 0;
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      if ((string) ($row['latest_snapshot'] ?? '') >= $threshold) $fresh++;
    }
    return max(0, count($projectIds) - $fresh);
  }

  /** @param list<string> $fields */
  private function tableHas(string $table, array $fields): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists($table)) return FALSE;
    foreach ($fields as $field) {
      if (!$schema->fieldExists($table, $field)) return FALSE;
    }
    return TRUE;
  }

}
