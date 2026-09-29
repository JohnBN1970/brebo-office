<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ThreeWayMatchRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseThreeWayMatchRepository implements ThreeWayMatchRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function invoiceLineContext(int $invoiceLineId): ?array {
    $query = $this->database->select('brebo_finance_purchase_invoice_line', 'il');
    $query->join('brebo_finance_purchase_invoice', 'i', 'i.id = il.invoice_id');
    $query->leftJoin('brebo_finance_commitment_line', 'cl', 'cl.id = il.commitment_line_id');
    $query->addField('i', 'project_nid');
    $query->addField('i', 'id', 'invoice_id');
    $query->fields('il');
    $query->addField('cl', 'amount_ex_vat', 'ordered_amount_ex_vat');
    $query->addField('cl', 'unit_price_ex_vat', 'ordered_unit_price_ex_vat');
    $query->addField('cl', 'vat_code', 'ordered_vat_code');
    $query->addField('cl', 'vat_rate', 'ordered_vat_rate');
    $row = $query->condition('il.id', $invoiceLineId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function verifiedPerformanceAmount(int $commitmentLineId): string {
    $query = $this->database->select('brebo_finance_performance_receipt', 'r');
    $query->condition('commitment_line_id', $commitmentLineId)
      ->condition('status', 'verified')
      ->condition('building_evidence_complete', 1)
      ->condition('quality_accepted', 1);
    $query->addExpression('COALESCE(SUM(amount_ex_vat), 0)', 'verified_total');
    return (string) $query->execute()->fetchField();
  }

  public function previouslyMatchedAmount(int $commitmentLineId, int $excludeLineId): string {
    $query = $this->database->select('brebo_finance_purchase_invoice_line', 'il');
    $query->condition('commitment_line_id', $commitmentLineId)
      ->condition('id', $excludeLineId, '<>')
      ->condition('match_status', 'matched');
    $query->addExpression('COALESCE(SUM(amount_ex_vat), 0)', 'matched_total');
    return (string) $query->execute()->fetchField();
  }

  public function updateInvoiceLine(int $invoiceLineId, array $values): void {
    $this->database->update('brebo_finance_purchase_invoice_line')->fields($values)->condition('id', $invoiceLineId)->execute();
  }

  public function invoiceMatchCounts(int $invoiceId): array {
    $query = $this->database->select('brebo_finance_purchase_invoice_line', 'il');
    $query->condition('invoice_id', $invoiceId);
    $query->addExpression("SUM(CASE WHEN match_status = 'exception' THEN 1 ELSE 0 END)", 'exceptions');
    $query->addExpression("SUM(CASE WHEN match_status = 'unmatched' THEN 1 ELSE 0 END)", 'unmatched');
    $row = $query->execute()->fetchAssoc() ?: [];
    return ['exceptions' => (int) ($row['exceptions'] ?? 0), 'unmatched' => (int) ($row['unmatched'] ?? 0)];
  }

  public function updateInvoice(int $invoiceId, array $values): void {
    $this->database->update('brebo_finance_purchase_invoice')->fields($values)->condition('id', $invoiceId)->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
