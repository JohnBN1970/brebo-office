<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\WorkingBudgetImportRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseWorkingBudgetImportRepository implements WorkingBudgetImportRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function calculationVersion(int $calculationId, string $calculationVersion): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'content_hash'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $calculationVersion)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function calculationSnapshot(int $calculationId, string $calculationVersion): ?array {
    $row = $this->database->select('brebo_calculation_snapshot', 's')
      ->fields('s', ['content_hash', 'payload'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $calculationVersion)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function workingBudgetExists(int $projectNid, int $calculationId, string $calculationVersion): bool {
    return (int) $this->database->select('brebo_finance_budget', 'b')
      ->condition('project_nid', $projectNid)
      ->condition('source_calculation_id', $calculationId)
      ->condition('source_calculation_version', $calculationVersion)
      ->countQuery()
      ->execute()
      ->fetchField() > 0;
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

  public function insertBudget(array $values): int {
    return (int) $this->database->insert('brebo_finance_budget')->fields($values)->execute();
  }

  public function insertBudgetLine(array $values): int {
    return (int) $this->database->insert('brebo_finance_budget_line')->fields($values)->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
