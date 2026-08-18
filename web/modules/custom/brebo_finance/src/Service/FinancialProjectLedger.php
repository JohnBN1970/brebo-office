<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Read-only project ledger for financial drill-down and audit navigation. */
final class FinancialProjectLedger {
  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function build(int $projectNid): array {
    return [
      'project_nid' => $projectNid,
      'commitments' => $this->rows('brebo_finance_commitment', $projectNid, ['id','commitment_number','supplier_name','status','amount_ex_vat','vat_amount','amount_inc_vat','created','changed']),
      'performance_receipts' => $this->rows('brebo_finance_performance_receipt', $projectNid, ['id','commitment_line_id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','evidence','verification_note','verified','verified_by','created','created_by','changed']),
      'change_orders' => $this->rows('brebo_finance_change_order', $projectNid, ['id','change_number','change_type','title','cause','consequence','status','sales_amount_ex_vat','cost_amount_ex_vat','created','changed']),
      'failure_costs' => $this->rows('brebo_finance_failure_cost', $projectNid, ['id','failure_number','category','title','cause','consequence','preventive_measure','status','gross_failure_cost_ex_vat','recoverable_amount_ex_vat','net_failure_cost_ex_vat','due_date','created','changed']),
      'payment_releases' => $this->rows('brebo_finance_payment_release', $projectNid, ['id','invoice_id','status','payment_amount','g_account_amount','blocked_amount','reason','created','changed']),
      'billing' => $this->rows('brebo_finance_billing', $projectNid, ['id','invoice_number','status','amount_ex_vat','vat_amount','amount_inc_vat','due_date','created','changed']),
      'audit' => $this->audit($projectNid),
      'basis' => 'Read-only ledger from registered BREBO Finance records. Missing tables return an empty section; values are not inferred.',
    ];
  }

  /** @return list<array<string, mixed>> */
  private function rows(string $table, int $projectNid, array $wanted): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists($table)) return [];
    $fields = array_values(array_filter($wanted, static fn(string $field): bool => $schema->fieldExists($table, $field)));
    if ($fields === [] || !$schema->fieldExists($table, 'project_nid')) return [];
    $query = $this->database->select($table, 'x')->fields('x', $fields)->condition('project_nid', $projectNid);
    if ($schema->fieldExists($table, 'changed')) $query->orderBy('changed', 'DESC');
    elseif ($schema->fieldExists($table, 'created')) $query->orderBy('created', 'DESC');
    return array_map(static fn(object $row): array => (array) $row, $query->execute()->fetchAll());
  }

  /** @return list<array<string, mixed>> */
  private function audit(int $projectNid): array {
    if (!$this->database->schema()->tableExists('brebo_finance_audit')) return [];
    $query = $this->database->select('brebo_finance_audit', 'a')->fields('a')
      ->condition('project_nid', $projectNid)->orderBy('created', 'DESC')->range(0, 100);
    return array_map(static fn(object $row): array => (array) $row, $query->execute()->fetchAll());
  }
}
