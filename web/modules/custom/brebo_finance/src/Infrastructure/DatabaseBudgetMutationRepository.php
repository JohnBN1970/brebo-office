<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BudgetMutationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseBudgetMutationRepository implements BudgetMutationRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function lockedWorkingBudget(int $budgetId): ?array {
    $row = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b')
      ->condition('id', $budgetId)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function editableMutation(int $mutationId): ?array {
    $row = $this->database->select('brebo_finance_budget_mutation', 'm')
      ->fields('m')
      ->condition('id', $mutationId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function budgetLineBelongsToBudget(int $lineId, int $budgetId): bool {
    return (bool) $this->database->select('brebo_finance_budget_line', 'l')
      ->condition('id', $lineId)
      ->condition('budget_id', $budgetId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function mutationHasLines(int $mutationId): bool {
    return (bool) $this->database->select('brebo_finance_budget_mutation_line', 'l')
      ->condition('mutation_id', $mutationId)
      ->countQuery()
      ->execute()
      ->fetchField();
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

  public function insertMutation(array $values): int {
    return (int) $this->database->insert('brebo_finance_budget_mutation')->fields($values)->execute();
  }

  public function insertMutationLine(array $values): int {
    return (int) $this->database->insert('brebo_finance_budget_mutation_line')->fields($values)->execute();
  }

  public function mutationTotals(int $mutationId): array {
    $query = $this->database->select('brebo_finance_budget_mutation_line', 'l');
    $query->condition('mutation_id', $mutationId);
    $query->addExpression('COALESCE(SUM(adjustment_ex_vat), 0)', 'amount_ex_vat');
    $query->addExpression('COALESCE(SUM(vat_amount), 0)', 'vat_amount');
    $query->addExpression('COALESCE(SUM(adjustment_inc_vat), 0)', 'amount_inc_vat');
    return $query->execute()->fetchAssoc() ?: [
      'amount_ex_vat' => '0',
      'vat_amount' => '0',
      'amount_inc_vat' => '0',
    ];
  }

  public function updateMutation(int $mutationId, array $values): void {
    $this->database->update('brebo_finance_budget_mutation')
      ->fields($values)
      ->condition('id', $mutationId)
      ->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
