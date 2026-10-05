<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialCockpitReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for the read-only project financial cockpit. */
final class DatabaseFinancialCockpitReadRepository implements FinancialCockpitReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestForecast(int $projectNid): ?array {
    $record = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f', [
        'id', 'snapshot_date', 'current_revenue_ex_vat', 'current_budget_ex_vat',
        'committed_ex_vat', 'verified_performance_ex_vat', 'invoiced_ex_vat',
        'paid_inc_vat', 'forecast_remaining_cost_ex_vat', 'risk_reserve_ex_vat',
        'forecast_end_cost_ex_vat', 'forecast_result_ex_vat', 'forecast_margin_pct',
        'content_hash', 'created', 'created_by',
      ])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')->range(0, 1)
      ->execute()->fetchAssoc();
    return $record === FALSE ? NULL : $record;
  }

  public function latestScenarioSnapshots(int $projectNid): array {
    $rows = $this->database->select('brebo_finance_scenario_snapshot', 'ss')
      ->fields('ss', [
        'id', 'scenario_id', 'forecast_snapshot_id', 'snapshot_date',
        'adjusted_revenue_ex_vat', 'adjusted_end_cost_ex_vat',
        'adjusted_risk_reserve_ex_vat', 'adjusted_result_ex_vat',
        'adjusted_margin_pct', 'receipt_delay_days', 'delayed_receipts_inc_vat',
        'content_hash',
      ])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $latest = [];
    foreach ($rows as $row) {
      $scenarioId = (int) $row['scenario_id'];
      if (!isset($latest[$scenarioId])) {
        $latest[$scenarioId] = $row;
      }
    }
    return array_values($latest);
  }

  public function verifiedCostObservationCount(int $projectNid): int {
    return (int) $this->database->select('brebo_finance_cost_observation', 'o')
      ->condition('project_nid', $projectNid)
      ->condition('quality_accepted', 1)
      ->countQuery()->execute()->fetchField();
  }

  public function observedCostCodes(int $projectNid): array {
    $values = $this->database->select('brebo_finance_cost_observation', 'o')
      ->distinct()->fields('o', ['cost_code'])
      ->condition('project_nid', $projectNid)
      ->condition('quality_accepted', 1)
      ->orderBy('cost_code')
      ->execute()->fetchCol();
    return array_values(array_map('strval', $values));
  }

  public function latestCostBenchmarks(int $projectNid): array {
    $costCodes = $this->observedCostCodes($projectNid);
    if ($costCodes === []) {
      return [];
    }
    $rows = $this->database->select('brebo_finance_cost_benchmark_snapshot', 'b')
      ->fields('b', [
        'id', 'cost_code', 'work_type', 'specification_hash', 'unit', 'region',
        'snapshot_date', 'sample_count', 'project_count', 'minimum_unit_cost_ex_vat',
        'benchmark_unit_cost_ex_vat', 'maximum_unit_cost_ex_vat', 'confidence_class',
        'content_hash',
      ])
      ->condition('cost_code', $costCodes, 'IN')
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $latest = [];
    foreach ($rows as $row) {
      $key = implode('|', [$row['cost_code'], $row['work_type'], $row['specification_hash'], $row['unit'], $row['region']]);
      if (!isset($latest[$key])) {
        $latest[$key] = $row;
      }
    }
    return array_values($latest);
  }

  public function latestSupplierScores(int $projectNid): array {
    $rows = $this->database->select('brebo_finance_supplier_score_snapshot', 's')
      ->fields('s', [
        'id', 'supplier_ref', 'supplier_name', 'snapshot_date', 'policy_version',
        'weighted_score', 'confidence_class', 'delivery_score', 'quality_score',
        'invoice_score', 'price_score', 'failure_cost_score', 'order_count',
        'receipt_count', 'invoice_count', 'content_hash',
      ])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $latest = [];
    foreach ($rows as $row) {
      $supplier = (string) $row['supplier_ref'];
      if (!isset($latest[$supplier])) {
        $latest[$supplier] = $row;
      }
    }
    return array_values($latest);
  }

  public function latestCashForecast(int $projectNid, string $scenario): ?array {
    $record = $this->database->select('brebo_finance_cash_forecast_snapshot', 's')
      ->fields('s', [
        'id', 'snapshot_date', 'scenario', 'opening_regular_balance',
        'opening_g_account_balance', 'lowest_regular_balance',
        'lowest_g_account_balance', 'first_regular_shortfall_date',
        'first_g_account_shortfall_date', 'content_hash', 'created', 'created_by',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('scenario', $scenario)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')->range(0, 1)
      ->execute()->fetchAssoc();
    return $record === FALSE ? NULL : $record;
  }

  public function sumByStatus(string $table, string $field, int $projectNid, array $statuses): string {
    $query = $this->database->select($table, 't');
    $query->condition('project_nid', $projectNid)->condition('status', $statuses, 'IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  public function sumExceptStatus(string $table, string $field, int $projectNid, array $statuses): string {
    $query = $this->database->select($table, 't');
    $query->condition('project_nid', $projectNid)->condition('status', $statuses, 'NOT IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  public function countByStatus(string $table, int $projectNid, array $statuses, string $statusField = 'status'): int {
    return (int) $this->database->select($table, 't')
      ->condition('project_nid', $projectNid)
      ->condition($statusField, $statuses, 'IN')
      ->countQuery()->execute()->fetchField();
  }

  public function countExceptStatus(string $table, int $projectNid, array $statuses): int {
    return (int) $this->database->select($table, 't')
      ->condition('project_nid', $projectNid)
      ->condition('status', $statuses, 'NOT IN')
      ->countQuery()->execute()->fetchField();
  }

}
