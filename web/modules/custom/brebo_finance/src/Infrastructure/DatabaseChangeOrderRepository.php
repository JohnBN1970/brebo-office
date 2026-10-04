<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ChangeOrderRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for controlled project change orders. */
final class DatabaseChangeOrderRepository implements ChangeOrderRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function approvedContractExists(int $projectNid, int $contractId): bool {
    return (int) $this->database->select('brebo_finance_project_contract', 'c')
      ->condition('id', $contractId)
      ->condition('project_nid', $projectNid)
      ->condition('status', 'approved')
      ->countQuery()
      ->execute()
      ->fetchField() === 1;
  }

  public function createChange(array $fields): int {
    return (int) $this->database->insert('brebo_finance_change_order')->fields($fields)->execute();
  }

  public function getChange(int $changeId): ?array {
    $row = $this->database->select('brebo_finance_change_order', 'c')
      ->fields('c')->condition('id', $changeId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateChange(int $changeId, array $fields): void {
    $this->database->update('brebo_finance_change_order')->fields($fields)->condition('id', $changeId)->execute();
  }

  public function createRevenueMutation(array $fields): void {
    $this->database->insert('brebo_finance_revenue_mutation')->fields($fields)->execute();
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

  public function atomic(callable $operation): mixed {
    $transaction = $this->database->startTransaction();
    try {
      $result = $operation();
      unset($transaction);
      return $result;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
