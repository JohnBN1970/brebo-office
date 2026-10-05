<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceImportRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for the Moneybird purchase-invoice mirror. */
final class DatabasePurchaseInvoiceImportRepository implements PurchaseInvoiceImportRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function findByMoneybirdId(string $moneybirdId): ?array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'source_hash', 'moneybird_id'])
      ->condition('moneybird_id', $moneybirdId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : [
      'id' => (int) $row['id'],
      'source_hash' => (string) ($row['source_hash'] ?? ''),
      'moneybird_id' => (string) ($row['moneybird_id'] ?? ''),
    ];
  }

  public function findBySupplierInvoice(?string $supplierRef, string $invoiceNumber): ?array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'source_hash', 'moneybird_id'])
      ->condition('supplier_ref', $supplierRef)
      ->condition('invoice_number', $invoiceNumber)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : [
      'id' => (int) $row['id'],
      'source_hash' => (string) ($row['source_hash'] ?? ''),
      'moneybird_id' => (string) ($row['moneybird_id'] ?? ''),
    ];
  }

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_finance_purchase_invoice')->fields($fields)->execute();
  }

  public function update(int $invoiceId, array $fields): void {
    $this->database->update('brebo_finance_purchase_invoice')->fields($fields)->condition('id', $invoiceId)->execute();
  }

  public function paymentRecipientInvoice(int $invoiceId): ?array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'project_nid', 'moneybird_id', 'supplier_ref', 'supplier_name', 'invoice_number'])
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function appendAuditIfAvailable(array $audit): void {
    if ($this->database->schema()->tableExists('brebo_finance_audit')) {
      $this->database->insert('brebo_finance_audit')->fields($audit)->execute();
    }
  }

}
