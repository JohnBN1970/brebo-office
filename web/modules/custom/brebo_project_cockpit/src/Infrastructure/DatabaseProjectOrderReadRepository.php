<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectOrderReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for project-order preparation reads. */
final class DatabaseProjectOrderReadRepository implements ProjectOrderReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function workingBudgetLines(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_budget') || !$this->database->schema()->tableExists('brebo_finance_budget_line')) {
      return [];
    }

    $budget = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', ['id'])
      ->condition('project_nid', $projectId)
      ->condition('budget_type', 'working')
      ->condition('status', 'locked')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($budget === FALSE) {
      return [];
    }

    $rows = array_values($this->database->select('brebo_finance_budget_line', 'l')
      ->fields('l')
      ->condition('budget_id', (int) $budget)
      ->orderBy('sort_order', 'ASC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));

    foreach ($rows as &$row) {
      $lineId = (int) $row['id'];
      $row['code'] = (string) ($row['cost_code'] ?? $row['line_number'] ?? $lineId);
      $row['remaining_ex_vat'] = max(
        0.0,
        (float) ($row['amount_ex_vat'] ?? 0)
          + $this->approvedMutationForBudgetLine($lineId)
          - $this->committedForBudgetLine($lineId),
      );
    }
    unset($row);
    return $rows;
  }

  private function committedForBudgetLine(int $budgetLineId): float {
    if (!$this->database->schema()->tableExists('brebo_finance_commitment_line') || !$this->database->schema()->tableExists('brebo_finance_commitment')) {
      return 0.0;
    }
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->condition('l.budget_line_id', $budgetLineId)
      ->condition('c.status', ['cancelled'], 'NOT IN')
      ->addExpression('COALESCE(SUM(l.amount_ex_vat), 0)', 'committed_total');
    return (float) $query->execute()->fetchField();
  }

  private function approvedMutationForBudgetLine(int $budgetLineId): float {
    if (!$this->database->schema()->tableExists('brebo_finance_budget_mutation_line') || !$this->database->schema()->tableExists('brebo_finance_budget_mutation')) {
      return 0.0;
    }
    $query = $this->database->select('brebo_finance_budget_mutation_line', 'ml');
    $query->join('brebo_finance_budget_mutation', 'm', 'm.id = ml.mutation_id');
    $query->condition('ml.budget_line_id', $budgetLineId)
      ->condition('m.status', 'approved')
      ->addExpression('COALESCE(SUM(ml.adjustment_ex_vat), 0)', 'approved_adjustment');
    return (float) $query->execute()->fetchField();
  }

}
