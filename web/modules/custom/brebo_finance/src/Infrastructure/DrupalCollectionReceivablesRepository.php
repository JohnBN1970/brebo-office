<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CollectionReceivablesRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Transitional adapter for collection transfer and invoice persistence. */
final class DrupalCollectionReceivablesRepository implements CollectionReceivablesRepositoryInterface {

  private const TRANSFER_STORE = 'brebo_finance.collection_transfer';
  private const AUDIT_STORE = 'brebo_finance.collection_reconciliation';

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public function transferStates(): array {
    return $this->keyValueFactory->get(self::TRANSFER_STORE)->getAll();
  }

  public function invoice(int $invoiceId): ?array {
    $row = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateInvoice(int $invoiceId, string $paidAmountIncVat, string $status, int $changedAt): void {
    $this->database->update('brebo_finance_sales_invoice')
      ->fields([
        'paid_amount_inc_vat' => $paidAmountIncVat,
        'status' => $status,
        'changed' => $changedAt,
        'changed_by' => 0,
      ])
      ->condition('id', $invoiceId)
      ->execute();
  }

  public function recordAudit(int $invoiceId, array $record): void {
    $this->keyValueFactory->get(self::AUDIT_STORE)->set((string) $invoiceId, $record);
  }

}
