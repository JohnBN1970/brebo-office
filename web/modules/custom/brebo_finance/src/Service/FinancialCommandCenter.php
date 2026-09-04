<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Builds a management-wide financial command center from verified sources. */
final class FinancialCommandCenter {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FinancialCockpitBuilder $cockpitBuilder,
    private readonly FinancialDecisionInbox $decisionInbox,
    private readonly FinancialDecisionAssignmentResolver $assignmentResolver,
    private readonly ReceivablesReconciliationMonitor $receivablesMonitor,
  ) {}

  /**
   * Lightweight dashboard payload using a fixed number of aggregate queries.
   *
   * @return array<string, mixed>
   */
  public function dashboard(AccountInterface $account): array {
    $projectIds = $this->viewableProjectIds($account);
    $portfolio = [
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

    $decisions = [];
    $decisionExposure = 0.0;
    $priority = ['now' => 0, 'today' => 0, 'this_week' => 0];
    foreach ($this->decisionInbox->pending() as $decision) {
      if (!in_array((int) ($decision['project_nid'] ?? 0), $projectIds, TRUE)) continue;
      $canAct = $this->assignmentResolver->canAct($account, (string) $decision['gate'], (string) $decision['authorization']['level']);
      if (!$canAct['authorized']) continue;
      $band = (string) ($decision['priority']['band'] ?? 'this_week');
      if (isset($priority[$band])) $priority[$band]++;
      $decisionExposure += (float) ($decision['exposure']['exposure_amount'] ?? 0);
      $decisions[] = $decision;
    }

    return [
      'generated_at' => time(),
      'portfolio' => $portfolio,
      'receivables_sync' => $this->receivablesSyncHealth(),
      'decisions' => [
        'count' => count($decisions),
        'now' => $priority['now'],
        'today' => $priority['today'],
        'this_week' => $priority['this_week'],
        'exposure_amount' => number_format($decisionExposure, 2, '.', ''),
        'top' => array_slice($decisions, 0, 10),
      ],
      'basis' => 'Bounded organisation-wide aggregate queries over projects the current user may view; no per-project cockpit rebuild is performed for the Finance dashboard.',
    ];
  }

  /** @return array<string, mixed> */
  public function build(AccountInterface $account): array {
    $projects = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'brebo_project']);
    $portfolio = [
      'project_count' => 0,
      'billable_not_invoiced_ex_vat' => 0.0,
      'invoiced_ex_vat' => 0.0,
      'committed_ex_vat' => 0.0,
      'open_contract_exposure_ex_vat' => 0.0,
      'net_failure_cost_ex_vat' => 0.0,
      'open_change_order_sales_ex_vat' => 0.0,
      'g_account_executed' => 0.0,
      'overdue_invoice_count' => 0,
      'pending_payment_releases' => 0,
      'forecast_stale_count' => 0,
    ];
    $rows = [];

    foreach ($projects as $project) {
      if (!$project->access('view', $account)) continue;
      $cockpit = $this->cockpitBuilder->build((int) $project->id());
      $portfolio['project_count']++;
      $portfolio['billable_not_invoiced_ex_vat'] += (float) ($cockpit['billing_position']['billable_not_invoiced_ex_vat'] ?? 0);
      $portfolio['invoiced_ex_vat'] += (float) ($cockpit['billing_position']['invoiced_ex_vat'] ?? 0);
      $portfolio['committed_ex_vat'] += (float) ($cockpit['procurement_pipeline']['committed_ex_vat'] ?? 0);
      $portfolio['open_contract_exposure_ex_vat'] += (float) ($cockpit['contract_obligations']['open_exposure_ex_vat'] ?? 0);
      $portfolio['net_failure_cost_ex_vat'] += (float) ($cockpit['failure_costs']['net_failure_cost_ex_vat'] ?? 0);
      $portfolio['open_change_order_sales_ex_vat'] += (float) ($cockpit['change_orders']['open_sales_ex_vat'] ?? 0);
      $portfolio['g_account_executed'] += (float) ($cockpit['g_account']['executed_amount'] ?? 0);
      $portfolio['overdue_invoice_count'] += (int) ($cockpit['billing_position']['overdue_count'] ?? 0);
      $portfolio['pending_payment_releases'] += (int) ($cockpit['workflow']['payment_releases_pending'] ?? 0);
      $portfolio['forecast_stale_count'] += !empty($cockpit['forecast_is_stale']) ? 1 : 0;
      $rows[] = [
        'project_nid' => (int) $project->id(),
        'title' => (string) $project->label(),
        'forecast' => $cockpit['forecast'],
        'forecast_is_stale' => (bool) $cockpit['forecast_is_stale'],
        'billing_position' => $cockpit['billing_position'],
        'procurement_pipeline' => $cockpit['procurement_pipeline'],
        'workflow' => $cockpit['workflow'],
        'g_account' => $cockpit['g_account'],
        'contract_obligations' => $cockpit['contract_obligations'],
        'failure_costs' => $cockpit['failure_costs'],
        'change_orders' => $cockpit['change_orders'],
        'cash_forecast' => $cockpit['cash_forecast'],
      ];
    }

    $decisions = [];
    $decisionExposure = 0.0;
    $priority = ['now' => 0, 'today' => 0, 'this_week' => 0];
    foreach ($this->decisionInbox->pending() as $decision) {
      $canAct = $this->assignmentResolver->canAct($account, (string) $decision['gate'], (string) $decision['authorization']['level']);
      if (!$canAct['authorized']) continue;
      $band = (string) ($decision['priority']['band'] ?? 'this_week');
      if (isset($priority[$band])) $priority[$band]++;
      $decisionExposure += (float) ($decision['exposure']['exposure_amount'] ?? 0);
      $decisions[] = $decision;
    }

    usort($rows, static fn(array $a, array $b): int => ((int) $b['forecast_is_stale'] <=> (int) $a['forecast_is_stale']) ?: ((int) ($b['workflow']['payment_releases_pending'] ?? 0) <=> (int) ($a['workflow']['payment_releases_pending'] ?? 0)));

    return [
      'generated_at' => time(),
      'portfolio' => $portfolio,
      'receivables_sync' => $this->receivablesSyncHealth(),
      'decisions' => [
        'count' => count($decisions),
        'now' => $priority['now'],
        'today' => $priority['today'],
        'this_week' => $priority['this_week'],
        'exposure_amount' => number_format($decisionExposure, 2, '.', ''),
        'top' => array_slice($decisions, 0, 10),
      ],
      'projects' => $rows,
      'basis' => 'Aggregated from project financial cockpits, live authorized financial decisions and Moneybird receivables synchronization health; no financial values are inferred when source records are absent.',
    ];
  }

  /** @return array<string, mixed> */
  public function receivablesSyncHealth(): array {
    $sync = $this->receivablesMonitor->status();
    $syncStatus = (string) ($sync['status'] ?? 'unknown');
    $lastSuccess = isset($sync['last_success_completed_at']) ? (int) $sync['last_success_completed_at'] : NULL;
    $ageSeconds = $lastSuccess !== NULL ? max(0, time() - $lastSuccess) : NULL;
    return [
      'status' => $syncStatus,
      'requires_attention' => $syncStatus === 'failed' || $lastSuccess === NULL || ($ageSeconds !== NULL && $ageSeconds > 86400),
      'last_attempt_completed_at' => isset($sync['completed_at']) ? (int) $sync['completed_at'] : NULL,
      'last_success_completed_at' => $lastSuccess,
      'last_success_age_seconds' => $ageSeconds,
      'last_success_summary' => is_array($sync['last_success_summary'] ?? NULL) ? $sync['last_success_summary'] : NULL,
      'error_code' => $syncStatus === 'failed' ? 'moneybird_receivables_sync_failed' : NULL,
      'operator_message' => $syncStatus === 'failed' ? 'De Moneybird debiteurensynchronisatie is mislukt. Controleer de beheerlogs of probeer de synchronisatie opnieuw.' : NULL,
    ];
  }

  /** @return list<int> */
  private function viewableProjectIds(AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'brebo_project')->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if ($project instanceof NodeInterface && $project->access('view', $account)) $result[] = (int) $project->id();
    }
    return $result;
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
