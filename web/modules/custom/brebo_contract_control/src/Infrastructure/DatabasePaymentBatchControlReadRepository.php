<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\PaymentBatchControlReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePaymentBatchControlReadRepository implements PaymentBatchControlReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function supplierInvoiceStorageAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_supplier_invoice');
  }

  public function supplierInvoice(int $invoiceId): ?array {
    if (!$this->supplierInvoiceStorageAvailable()) {
      return NULL;
    }

    $row = $this->database
      ->select('brebo_supplier_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

}
