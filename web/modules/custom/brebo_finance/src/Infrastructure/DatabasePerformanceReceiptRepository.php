<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PerformanceReceiptRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePerformanceReceiptRepository implements PerformanceReceiptRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function commitmentLineContext(int $commitmentLineId): ?array {
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->fields('l');
    $query->addField('c', 'project_nid');
    $query->addField('c', 'id', 'commitment_id');
    $row = $query->condition('l.id', $commitmentLineId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function receipt(int $receiptId): ?array {
    $row = $this->database->select('brebo_finance_performance_receipt', 'r')
      ->fields('r')
      ->condition('id', $receiptId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function registeredAmount(int $commitmentLineId): string {
    $query = $this->database->select('brebo_finance_performance_receipt', 'r');
    $query->condition('commitment_line_id', $commitmentLineId)
      ->condition('status', ['rejected'], 'NOT IN');
    $query->addExpression('COALESCE(SUM(amount_ex_vat), 0)', 'registered_total');
    return (string) $query->execute()->fetchField();
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

  public function insertReceipt(array $values): int {
    return (int) $this->database->insert('brebo_finance_performance_receipt')->fields($values)->execute();
  }

  public function updateReceipt(int $receiptId, array $values): void {
    $this->database->update('brebo_finance_performance_receipt')->fields($values)->condition('id', $receiptId)->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
