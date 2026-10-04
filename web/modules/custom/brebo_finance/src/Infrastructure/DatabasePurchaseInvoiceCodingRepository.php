<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceCodingRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for purchase-invoice coding. */
final class DatabasePurchaseInvoiceCodingRepository implements PurchaseInvoiceCodingRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function invoice(int $invoiceId): ?array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateInvoice(int $invoiceId, array $fields): void {
    $this->database->update('brebo_finance_purchase_invoice')->fields($fields)->condition('id', $invoiceId)->execute();
  }

  public function updateInvoiceLines(int $invoiceId, array $fields): void {
    $this->database->update('brebo_finance_purchase_invoice_line')->fields($fields)->condition('invoice_id', $invoiceId)->execute();
  }

  public function invoiceLineId(int $invoiceId, int $lineNumber): ?int {
    $id = $this->database->select('brebo_finance_purchase_invoice_line', 'l')->fields('l', ['id'])
      ->condition('invoice_id', $invoiceId)->condition('line_number', $lineNumber)->execute()->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function updateLine(int $lineId, array $fields): void {
    $this->database->update('brebo_finance_purchase_invoice_line')->fields($fields)->condition('id', $lineId)->execute();
  }

  public function createLine(array $fields): int {
    return (int) $this->database->insert('brebo_finance_purchase_invoice_line')->fields($fields)->execute();
  }

  public function invoiceOwnsLine(int $invoiceId, int $lineId): bool {
    return $this->database->select('brebo_finance_purchase_invoice_line', 'l')->fields('l', ['id'])
      ->condition('id', $lineId)->condition('invoice_id', $invoiceId)->execute()->fetchField() !== FALSE;
  }

  public function commitmentLineForProject(int $commitmentLineId, int $projectNid): ?array {
    $query = $this->database->select('brebo_finance_commitment_line', 'cl');
    $query->join('brebo_finance_commitment', 'c', 'c.id = cl.commitment_id');
    $query->addField('cl', 'id');
    $query->addField('c', 'id', 'commitment_id');
    $row = $query->condition('cl.id', $commitmentLineId)->condition('c.project_nid', $projectNid)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : ['id' => (int) $row['id'], 'commitment_id' => (int) $row['commitment_id']];
  }

  public function appendAudit(array $audit): void {
    $this->database->insert('brebo_finance_audit')->fields($audit)->execute();
  }

}
