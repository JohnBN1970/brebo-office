<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceOutboxRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseSalesInvoiceOutboxRepository implements SalesInvoiceOutboxRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function outbox(int $outboxId): ?array {
    $row = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')
      ->fields('o')
      ->condition('id', $outboxId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateOutbox(int $outboxId, array $fields): void {
    $this->database->update('brebo_finance_sales_invoice_outbox')
      ->fields($fields)
      ->condition('id', $outboxId)
      ->execute();
  }

  public function updateDraft(int $draftId, array $fields): void {
    $this->database->update('brebo_finance_sales_invoice_draft')
      ->fields($fields)
      ->condition('id', $draftId)
      ->execute();
  }

  public function transactional(callable $callback): mixed {
    $transaction = $this->database->startTransaction();
    try {
      return $callback();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
