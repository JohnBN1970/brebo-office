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
    $forecast = $this->database->select('brebo_finance_forecast_snapshot', 'f')->fields('f')->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();

    $blockers = [];
    if ($forecast === FALSE) {
      $blockers[] = ['code' => 'forecast_missing', 'label' => 'Definitieve financiële forecast ontbreekt.'];
    }
    else {
      if ((string) $forecast['forecast_remaining_cost_ex_vat'] !== '0.0000' || (string) $forecast['risk_reserve_ex_vat'] !== '0.0000') {
        $blockers[] = ['code' => 'forecast_not_final', 'label' => 'Forecast bevat nog resterende kosten of risicoreserve.'];
      }
      if ($this->forecastIsStale($projectNid, $forecast)) {
        $blockers[] = ['code' => 'forecast_stale', 'label' => 'Financiële brongegevens zijn gewijzigd na de laatste forecast. Maak eerst een nieuwe forecast.'];
      }
    }

    $checks = [
      ['commitments_open', 'brebo_finance_commitment', ['cancelled', 'closed'], 'Open commitments'],
      ['performances_open', 'brebo_finance_performance_receipt', ['verified', 'rejected', 'cancelled'], 'Niet-afgesloten prestaties'],
      ['purchase_invoices_open', 'brebo_finance_purchase_invoice', ['paid', 'cancelled', 'credited'], 'Open inkoopfacturen'],
      ['payment_releases_open', 'brebo_finance_payment_release', ['executed', 'rejected', 'cancelled'], 'Open betaalvrijgaven'],
      ['billing_instalments_open', 'brebo_finance_billing_instalment', ['invoiced', 'paid', 'cancelled'], 'Nog te factureren termijnen'],
      ['sales_invoices_open', 'brebo_finance_sales_invoice', ['paid', 'cancelled', 'credited'], 'Open verkoopfacturen/debiteuren'],
      ['sales_invoice_drafts_open', 'brebo_finance_sales_invoice_draft', ['sent', 'cancelled'], 'Open verkoopfactuurconcepten'],
      ['sales_invoice_commands_open', 'brebo_finance_sales_invoice_outbox', ['completed'], 'Nog niet afgeronde Moneybird factuurcommando’s'],
      ['budget_mutations_open', 'brebo_finance_budget_mutation', ['approved', 'rejected', 'cancelled'], 'Open budgetmutaties'],
      ['contract_obligations_open', 'brebo_finance_contract_obligation', ['verified', 'waived', 'closed'], 'Open contractverplichtingen'],
      ['failure_costs_open', 'brebo_finance_failure_cost', ['closed', 'rejected'], 'Open faalkosten'],
    ];
    foreach ($checks as [$code, $table, $closedStatuses, $label]) {
      if (!$this->database->schema()->tableExists($table)) continue;
      $count = (int) $this->database->select($table, 't')->condition('project_nid', $projectNid)->condition('status', $closedStatuses, 'NOT IN')->countQuery()->execute()->fetchField();
      if ($count > 0) $blockers[] = ['code' => $code, 'label' => $label, 'count' => $count];
    }

    return [
      'project_nid' => $projectNid,
      'closable' => $blockers === [],
      'blockers' => $blockers,
      'final_forecast' => $forecast === FALSE ? NULL : [
        'id' => (int) $forecast['id'], 'snapshot_date' => (string) $forecast['snapshot_date'],
        'revenue_ex_vat' => (string) $forecast['current_revenue_ex_vat'], 'end_cost_ex_vat' => (string) $forecast['forecast_end_cost_ex_vat'],
        'result_ex_vat' => (string) $forecast['forecast_result_ex_vat'], 'margin_pct' => (string) $forecast['forecast_margin_pct'],
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
    $payload = [
      'project_nid' => $projectNid, 'forecast_snapshot_id' => $forecast['id'], 'final_revenue_ex_vat' => $forecast['revenue_ex_vat'],
      'final_cost_ex_vat' => $forecast['end_cost_ex_vat'], 'final_result_ex_vat' => $forecast['result_ex_vat'], 'final_margin_pct' => $forecast['margin_pct'],
      'forecast_hash' => $forecast['content_hash'], 'closure_note' => $note,
    ];
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

  /** @param array<string, mixed> $forecast */
  private function forecastIsStale(int $projectNid, array $forecast): bool {
    $payload = json_decode((string) ($forecast['payload'] ?? ''), TRUE);
    $storedHash = is_array($payload) ? ($payload['source_state_hash'] ?? NULL) : NULL;
    if (is_string($storedHash) && $storedHash !== '') {
      return !hash_equals($storedHash, $this->financialSourceStateHash($projectNid));
    }
    return $this->hasFinancialChangesAfter($projectNid, (int) $forecast['created']);
  }

  /** Backward-compatible fallback for forecasts created before source hashing. */
  private function hasFinancialChangesAfter(int $projectNid, int $forecastCreated): bool {
    $schema = $this->database->schema();
    foreach ($this->sourceTables() as $table) {
      if (!$schema->tableExists($table) || !$schema->fieldExists($table, 'project_nid')) continue;
      $timeField = NULL;
      foreach (['changed', 'recorded_at', 'created'] as $candidate) {
        if ($schema->fieldExists($table, $candidate)) { $timeField = $candidate; break; }
      }
      if ($timeField === NULL) continue;
      $query = $this->database->select($table, 't')->condition('project_nid', $projectNid);
      $query->addExpression("COALESCE(MAX($timeField), 0)", 'latest_change');
      if ((int) $query->execute()->fetchField() > $forecastCreated) return TRUE;
    }
    return FALSE;
  }

  private function financialSourceStateHash(int $projectNid): string {
    $schema = $this->database->schema();
    $state = [];
    foreach ($this->sourceTables() as $table) {
      if (!$schema->tableExists($table) || !$schema->fieldExists($table, 'project_nid')) continue;
      $query = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectNid);
      if ($schema->fieldExists($table, 'id')) $query->orderBy('id', 'ASC');
      $state[$table] = $query->execute()->fetchAll();
    }
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
  }

  /** @return string[] */
  private function sourceTables(): array {
    return [
      'brebo_finance_project_contract', 'brebo_finance_revenue_mutation', 'brebo_finance_budget', 'brebo_finance_commitment',
      'brebo_finance_performance_receipt', 'brebo_finance_purchase_invoice', 'brebo_finance_payment_release', 'brebo_finance_billing_instalment',
      'brebo_finance_sales_invoice', 'brebo_finance_sales_invoice_draft', 'brebo_finance_sales_invoice_outbox', 'brebo_finance_budget_mutation',
      'brebo_finance_contract_obligation', 'brebo_finance_failure_cost', 'brebo_finance_change_order', 'brebo_finance_provisional_sum',
    ];
  }
}
