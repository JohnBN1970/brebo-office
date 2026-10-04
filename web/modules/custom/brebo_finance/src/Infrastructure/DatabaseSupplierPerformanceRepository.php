<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SupplierPerformanceRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for supplier performance scoring. */
final class DatabaseSupplierPerformanceRepository implements SupplierPerformanceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function snapshotExists(int $projectNid, string $supplierRef, string $snapshotDate, string $policyVersion): bool {
    return (int) $this->database->select('brebo_finance_supplier_score_snapshot', 's')
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('snapshot_date', $snapshotDate)
      ->condition('policy_version', trim($policyVersion))
      ->countQuery()->execute()->fetchField() > 0;
  }

  public function orders(int $projectNid, string $supplierRef): array {
    return $this->database->select('brebo_finance_commitment', 'c')
      ->fields('c', ['id', 'amount_ex_vat', 'delivery_date'])
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('status', 'cancelled', '<>')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function receipts(int $projectNid, array $commitmentIds): array {
    if ($commitmentIds === []) return [];
    $query = $this->database->select('brebo_finance_performance_receipt', 'r');
    $query->join('brebo_finance_commitment_line', 'l', 'l.id = r.commitment_line_id');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->fields('r', ['id', 'performance_date', 'quality_accepted']);
    $query->addField('c', 'delivery_date');
    $query->condition('r.project_nid', $projectNid);
    $query->condition('r.status', 'verified');
    $query->condition('c.id', $commitmentIds, 'IN');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function invoices(int $projectNid, string $supplierRef): array {
    return $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'amount_ex_vat', 'match_status'])
      ->condition('project_nid', $projectNid)
      ->condition('supplier_ref', trim($supplierRef))
      ->condition('status', 'cancelled', '<>')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function invoiceVariance(array $invoiceIds): string {
    if ($invoiceIds === []) return '0.0000';
    $query = $this->database->select('brebo_finance_purchase_invoice_line', 'l');
    $query->condition('invoice_id', $invoiceIds, 'IN');
    $query->addExpression('COALESCE(SUM(ABS(variance_amount_ex_vat)), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

  public function failureCost(int $projectNid, string $supplierRef): string {
    $query = $this->database->select('brebo_finance_failure_cost', 'f');
    $query->condition('project_nid', $projectNid);
    $query->condition('responsible_party_ref', trim($supplierRef));
    $query->condition('status', ['validated', 'recovery_pending', 'closed'], 'IN');
    $query->addExpression('COALESCE(SUM(net_failure_cost_ex_vat), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

  public function createSnapshot(array $fields): int {
    return (int) $this->database->insert('brebo_finance_supplier_score_snapshot')->fields($fields)->execute();
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
