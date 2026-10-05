<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceIntakeRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for source-neutral purchase-invoice intake. */
final class DatabasePurchaseInvoiceIntakeRepository implements PurchaseInvoiceIntakeRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function available(): bool {
    return $this->database->schema()->tableExists('brebo_finance_purchase_invoice');
  }

  public function findBySourceHash(string $sourceHash): ?int {
    $id = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id'])
      ->condition('source_hash', $sourceHash)
      ->range(0, 1)
      ->execute()->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function findBySupplierInvoice(string $supplierRef, string $invoiceNumber): array {
    return array_map('intval', $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id'])
      ->condition('supplier_ref', $supplierRef)
      ->condition('invoice_number', $invoiceNumber)
      ->execute()->fetchCol());
  }

  public function createInvoice(array $invoiceFields, array $lines): int {
    $transaction = $this->database->startTransaction();
    try {
      $invoiceId = (int) $this->database->insert('brebo_finance_purchase_invoice')->fields($invoiceFields)->execute();
      if ($lines !== [] && $this->database->schema()->tableExists('brebo_finance_purchase_invoice_line')) {
        foreach ($lines as $line) {
          $this->database->insert('brebo_finance_purchase_invoice_line')
            ->fields($line + ['invoice_id' => $invoiceId])
            ->execute();
        }
      }
      unset($transaction);
      return $invoiceId;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function findDuplicate(?string $sourceHash, string $supplierRef, string $invoiceNumber): ?int {
    if ($sourceHash !== NULL) {
      $id = $this->findBySourceHash($sourceHash);
      if ($id !== NULL) {
        return $id;
      }
    }
    $ids = $this->findBySupplierInvoice($supplierRef, $invoiceNumber);
    return $ids !== [] ? $ids[0] : NULL;
  }

  public function appendAuditIfAvailable(array $audit): void {
    if ($this->database->schema()->tableExists('brebo_finance_audit')) {
      $this->database->insert('brebo_finance_audit')->fields($audit)->execute();
    }
  }

}
