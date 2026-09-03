<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use RuntimeException;

/** Builds and records an evidence-backed financial project closure. */
final class ProjectFinancialClosureManager {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function assess(int $projectNid): array {
    $forecast = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f')
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()->fetchAssoc();

    $blockers = [];
    if ($forecast === FALSE) {
      $blockers[] = ['code' => 'forecast_missing', 'label' => 'Definitieve financiële forecast ontbreekt.'];
    }
    elseif ((string) $forecast['forecast_remaining_cost_ex_vat'] !== '0.0000' || (string) $forecast['risk_reserve_ex_vat'] !== '0.0000') {
      $blockers[] = ['code' => 'forecast_not_final', 'label' => 'Forecast bevat nog resterende kosten of risicoreserve.'];
    }

    $checks = [
      ['commitments_open', 'brebo_finance_commitment', ['cancelled', 'closed'], 'Open commitments'],
      ['performances_open', 'brebo_finance_performance_receipt', ['verified', 'rejected', 'cancelled'], 'Niet-afgesloten prestaties'],
      ['purchase_invoices_open', 'brebo_finance_purchase_invoice', ['paid', 'cancelled', 'credited'], 'Open inkoopfacturen'],
      ['payment_releases_open', 'brebo_finance_payment_release', ['executed', 'rejected', 'cancelled'], 'Open betaalvrijgaven'],
      ['billing_instalments_open', 'brebo_finance_billing_instalment', ['invoiced', 'cancelled'], 'Nog te factureren termijnen'],
      ['sales_invoices_open', 'brebo_finance_sales_invoice', ['paid', 'cancelled', 'credited'], 'Open verkoopfacturen/debiteuren'],
      ['budget_mutations_open', 'brebo_finance_budget_mutation', ['approved', 'rejected', 'cancelled'], 'Open budgetmutaties'],
      ['contract_obligations_open', 'brebo_finance_contract_obligation', ['verified', 'waived', 'closed'], 'Open contractverplichtingen'],
      ['failure_costs_open', 'brebo_finance_failure_cost', ['closed', 'rejected'], 'Open faalkosten'],
    ];
    foreach ($checks as [$code, $table, $closedStatuses, $label]) {
      if (!$this->database->schema()->tableExists($table)) continue;
      $query = $this->database->select($table, 't')->condition('project_nid', $projectNid)->condition('status', $closedStatuses, 'NOT IN');
      $count = (int) $query->countQuery()->execute()->fetchField();
      if ($count > 0) $blockers[] = ['code' => $code, 'label' => $label, 'count' => $count];
    }

    return [
      'project_nid' => $projectNid,
      'closable' => $blockers === [],
      'blockers' => $blockers,
      'final_forecast' => $forecast === FALSE ? NULL : [
        'id' => (int) $forecast['id'],
        'snapshot_date' => (string) $forecast['snapshot_date'],
        'revenue_ex_vat' => (string) $forecast['current_revenue_ex_vat'],
        'end_cost_ex_vat' => (string) $forecast['forecast_end_cost_ex_vat'],
        'result_ex_vat' => (string) $forecast['forecast_result_ex_vat'],
        'margin_pct' => (string) $forecast['forecast_margin_pct'],
        'content_hash' => (string) $forecast['content_hash'],
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function close(int $projectNid, int $userId, string $note): array {
    $note = trim($note);
    if ($note === '') throw new RuntimeException('Een afsluitnotitie is verplicht.');
    $assessment = $this->assess($projectNid);
    if (!$assessment['closable']) throw new RuntimeException('Project kan financieel nog niet worden afgesloten.');

    $existing = $this->database->select('brebo_finance_project_closure', 'c')->fields('c')->condition('project_nid', $projectNid)->execute()->fetchAssoc();
    if ($existing !== FALSE) return $existing;

    $forecast = $assessment['final_forecast'];
    $payload = ['project_nid' => $projectNid, 'forecast_snapshot_id' => $forecast['id'], 'final_revenue_ex_vat' => $forecast['revenue_ex_vat'], 'final_cost_ex_vat' => $forecast['end_cost_ex_vat'], 'final_result_ex_vat' => $forecast['result_ex_vat'], 'final_margin_pct' => $forecast['margin_pct'], 'forecast_hash' => $forecast['content_hash'], 'closure_note' => $note];
    $payload['content_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    $payload += ['closed' => time(), 'closed_by' => $userId];
    $id = (int) $this->database->insert('brebo_finance_project_closure')->fields($payload)->execute();
    return $this->database->select('brebo_finance_project_closure', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc() ?: $payload;
  }

  /** @return array<string, mixed>|null */
  public function closure(int $projectNid): ?array {
    $row = $this->database->select('brebo_finance_project_closure', 'c')->fields('c')->condition('project_nid', $projectNid)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }
}
