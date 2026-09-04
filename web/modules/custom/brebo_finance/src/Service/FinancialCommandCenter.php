<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Builds a management-wide financial command center from project cockpits. */
final class FinancialCommandCenter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FinancialCockpitBuilder $cockpitBuilder,
    private readonly FinancialDecisionInbox $decisionInbox,
    private readonly FinancialDecisionAssignmentResolver $assignmentResolver,
    private readonly ReceivablesReconciliationMonitor $receivablesMonitor,
  ) {}

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
}
