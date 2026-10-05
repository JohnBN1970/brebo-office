<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialControlScannerRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for automatic financial controls. */
final class DatabaseFinancialControlScannerRepository implements FinancialControlScannerRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function hasLockedBudget(int $projectNid): bool {
    return (bool) $this->database->select('brebo_finance_budget', 'b')
      ->condition('project_nid', $projectNid)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->countQuery()->execute()->fetchField();
  }

  public function openContractObligations(int $projectNid): array {
    return $this->database->select('brebo_finance_contract_obligation', 'o')
      ->fields('o', ['id','obligation_number','obligation_type','responsible_side','status','title','clause_ref','consequence','control_measure','due_date','financial_exposure_ex_vat','owner_uid'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['open','pending_verification','waiver_review'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function scenarioSnapshots(int $projectNid): array {
    return $this->database->select('brebo_finance_scenario_snapshot', 'ss')
      ->fields('ss', ['id','scenario_id','snapshot_date','adjusted_result_ex_vat','adjusted_margin_pct','receipt_delay_days','delayed_receipts_inc_vat'])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date','DESC')->orderBy('id','DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function openBillingInstalments(int $projectNid): array {
    return $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i', ['id','instalment_number','description','status','planned_invoice_date','amount_ex_vat','amount_inc_vat','billable_at'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['planned','billable'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function openSalesInvoices(int $projectNid): array {
    return $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id','invoice_number','status','due_date','amount_inc_vat','paid_amount_inc_vat','dispute_reason'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['sent','overdue','disputed'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function supplierScoreSnapshots(int $projectNid): array {
    return $this->database->select('brebo_finance_supplier_score_snapshot', 's')
      ->fields('s', ['id','supplier_ref','supplier_name','snapshot_date','weighted_score','confidence_class','delivery_score','quality_score','invoice_score','price_score','failure_cost_score','policy_payload'])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date','DESC')->orderBy('id','DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function openFailureCosts(int $projectNid): array {
    return $this->database->select('brebo_finance_failure_cost', 'f')
      ->fields('f', ['id','failure_number','status','category','title','total_cost_ex_vat','recoverable_amount_ex_vat','recovered_amount_ex_vat','net_failure_cost_ex_vat','owner_uid','due_date','created'])
      ->condition('project_nid', $projectNid)
      ->condition('status', 'closed', '<>')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function activeChangeOrders(int $projectNid): array {
    return $this->database->select('brebo_finance_change_order', 'c')
      ->fields('c', ['id','change_number','status','title','sales_amount_ex_vat','margin_amount_ex_vat','offered_at','executed_at','created'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['client_rejected','paid'], 'NOT IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function budgetMutationExists(int $projectNid, string $mutationNumber): bool {
    return (int) $this->database->select('brebo_finance_budget_mutation', 'm')
      ->condition('project_nid', $projectNid)
      ->condition('mutation_number', $mutationNumber)
      ->countQuery()->execute()->fetchField() > 0;
  }

  public function latestCommittedCashForecast(int $projectNid): ?array {
    $row = $this->database->select('brebo_finance_cash_forecast_snapshot', 'c')
      ->fields('c', ['id','snapshot_date','lowest_regular_balance','lowest_g_account_balance','first_regular_shortfall_date','first_g_account_shortfall_date'])
      ->condition('project_nid', $projectNid)
      ->condition('scenario', 'committed')
      ->orderBy('snapshot_date','DESC')->orderBy('id','DESC')->range(0,1)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function overdueReceivables(int $projectNid, string $today): array {
    return $this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e', ['id','description','amount_inc_vat','due_date','source_type','source_id'])
      ->condition('project_nid', $projectNid)
      ->condition('direction', 'incoming')
      ->condition('status', 'confirmed')
      ->condition('due_date', $today, '<')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function openPurchaseInvoices(int $projectNid): array {
    return $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id','invoice_number','match_status','status','due_date','amount_inc_vat'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['cancelled','paid'], 'NOT IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function staleBudgetMutations(int $projectNid, int $createdBefore): array {
    return $this->database->select('brebo_finance_budget_mutation', 'm')
      ->fields('m', ['id','mutation_number','amount_ex_vat','created'])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['draft','in_review'], 'IN')
      ->condition('created', $createdBefore, '<')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function expiredGAccountInstructions(int $projectNid, string $today): array {
    $query = $this->database->select('brebo_finance_g_account_instruction', 'g');
    $query->fields('g', ['id','effective_until','counterparty_name']);
    $query->condition('project_nid', $projectNid);
    $query->condition('status', 'approved');
    $query->isNotNull('effective_until');
    $query->condition('effective_until', $today, '<');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function latestForecastDate(int $projectNid): ?string {
    $value = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f', ['snapshot_date'])
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->range(0,1)
      ->execute()->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

  public function findingStatus(int $projectNid, string $code, string $sourceType, int $sourceId): ?string {
    $value = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', ['status'])
      ->condition('project_nid', $projectNid)
      ->condition('control_code', $code)
      ->condition('source_type', $sourceType)
      ->condition('source_id', $sourceId)
      ->execute()->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

  public function upsertFinding(array $keys, array $insertFields, array $fields): void {
    $this->database->merge('brebo_finance_control_finding')
      ->keys($keys)->insertFields($insertFields)->fields($fields)->execute();
  }

  public function openAutomaticFindings(int $projectNid, string $origin): array {
    return $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', ['id','control_code','source_type','source_id'])
      ->condition('project_nid', $projectNid)
      ->condition('status', 'open')
      ->condition('origin', $origin)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function updateFinding(int $findingId, array $fields): void {
    $this->database->update('brebo_finance_control_finding')->fields($fields)->condition('id', $findingId)->execute();
  }

}
