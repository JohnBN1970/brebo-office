<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceSupplierSourceRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for supplier references present on purchase invoices. */
final class DatabasePurchaseInvoiceSupplierSourceRepository implements PurchaseInvoiceSupplierSourceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function snapshot(): array {
    $rows = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['supplier_ref', 'supplier_name'])
      ->condition('supplier_ref', '', '<>')
      ->orderBy('supplier_name')
      ->execute()
      ->fetchAllAssoc('supplier_ref');

    $suppliers = [];
    foreach ($rows as $row) {
      $suppliers[] = [
        'contact_id' => trim((string) $row->supplier_ref),
        'name' => trim((string) $row->supplier_name),
      ];
    }

    return [
      'invoice_count' => (int) $this->database->select('brebo_finance_purchase_invoice', 'i')->countQuery()->execute()->fetchField(),
      'suppliers' => $suppliers,
    ];
  }

}
