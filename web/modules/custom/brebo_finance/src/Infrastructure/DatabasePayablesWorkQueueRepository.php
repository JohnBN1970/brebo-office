<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\Core\Database\Connection;
use Drupal\brebo_finance\Contract\PayablesWorkQueueRepositoryInterface;

/** Drupal database adapter for the operational payables work queue. */
final class DatabasePayablesWorkQueueRepository implements PayablesWorkQueueRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function purchaseInvoiceTableExists(): bool {
    return $this->database->schema()->tableExists('brebo_finance_purchase_invoice');
  }

  public function openInvoices(): array {
    return $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('status', ['paid', 'cancelled'], 'NOT IN')
      ->orderBy('due_date')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
