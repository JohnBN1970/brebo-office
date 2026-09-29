<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\WorkingBudgetApprovalRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseWorkingBudgetApprovalRepository implements WorkingBudgetApprovalRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function budget(int $budgetId): ?array {
    $row = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', ['id', 'project_nid', 'budget_type', 'status'])
      ->condition('id', $budgetId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
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

  public function saveDecision(int $budgetId, string $discipline, array $values): void {
    $this->database->merge('brebo_finance_budget_approval')
      ->keys(['budget_id' => $budgetId, 'discipline' => $discipline])
      ->fields($values)
      ->execute();
  }

  public function decisions(int $budgetId): array {
    return $this->database->select('brebo_finance_budget_approval', 'a')
      ->fields('a', ['discipline', 'decision'])
      ->condition('budget_id', $budgetId)
      ->execute()
      ->fetchAllKeyed();
  }

  public function hasInvalidLabourBaseline(int $budgetId): bool {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->fields('l', ['id']);
    $query->condition('budget_id', $budgetId);
    $query->condition('cost_code', 'arbeid');
    $invalid = $query->orConditionGroup()
      ->condition('budget_hours', '0.0000', '<=')
      ->condition('hourly_cost_ex_vat', '0.0000', '<=');
    $query->condition($invalid);
    return (int) $query->countQuery()->execute()->fetchField() > 0;
  }

  public function baselineRows(int $budgetId): array {
    return $this->database->select('brebo_finance_budget_line', 'l')
      ->fields('l', [
        'line_key',
        'parent_line_id',
        'cost_code',
        'work_package',
        'description',
        'quantity',
        'unit',
        'unit_cost_ex_vat',
        'amount_ex_vat',
        'budget_hours',
        'hourly_cost_ex_vat',
        'vat_code',
        'vat_rate',
        'vat_amount',
        'amount_inc_vat',
        'vat_reverse_charge',
        'non_deductible_vat_amount',
        'source_line_ref',
        'sort_order',
      ])
      ->condition('budget_id', $budgetId)
      ->orderBy('sort_order')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function updateBudget(int $budgetId, array $values): void {
    $this->database->update('brebo_finance_budget')
      ->fields($values)
      ->condition('id', $budgetId)
      ->execute();
  }

  public function insertAudit(array $values): void {
    $this->database->insert('brebo_finance_audit')->fields($values)->execute();
  }

}
