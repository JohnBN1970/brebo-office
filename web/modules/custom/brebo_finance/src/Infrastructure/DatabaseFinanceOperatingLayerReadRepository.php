<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinanceOperatingLayerReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinanceOperatingLayerReadRepository implements FinanceOperatingLayerReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function overview(int $projectNid): array {
    $budget = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b')
      ->condition('project_nid', $projectNid)
      ->condition('budget_type', 'working')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $budgetId = $budget !== FALSE ? (int) $budget['id'] : 0;
    $lines = $budgetId > 0
      ? array_values($this->database->select('brebo_finance_budget_line', 'l')
        ->fields('l')
        ->condition('budget_id', $budgetId)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC))
      : [];

    $approvals = $budgetId > 0
      ? array_values($this->database->select('brebo_finance_budget_approval', 'a')
        ->fields('a')
        ->condition('budget_id', $budgetId)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC))
      : [];

    $commitments = array_values($this->database->select('brebo_finance_commitment', 'c')
      ->fields('c')
      ->condition('project_nid', $projectNid)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));

    foreach ($commitments as &$commitment) {
      $commitment['lines'] = array_values($this->database->select('brebo_finance_commitment_line', 'l')
        ->fields('l')
        ->condition('commitment_id', (int) $commitment['id'])
        ->orderBy('line_number')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC));
    }
    unset($commitment);

    return [
      'project_nid' => $projectNid,
      'working_budget' => $budget !== FALSE ? $budget : NULL,
      'budget_lines' => $lines,
      'approvals' => $approvals,
      'commitments' => $commitments,
    ];
  }

  public function budgetBelongsToProject(int $budgetId, int $projectNid): bool {
    $ownerProject = $this->database->select('brebo_finance_budget', 'b')
      ->fields('b', ['project_nid'])
      ->condition('id', $budgetId)
      ->condition('budget_type', 'working')
      ->execute()
      ->fetchField();
    return $ownerProject !== FALSE && (int) $ownerProject === $projectNid;
  }

  public function commitmentBelongsToProject(int $commitmentId, int $projectNid): bool {
    $ownerProject = $this->database->select('brebo_finance_commitment', 'c')
      ->fields('c', ['project_nid'])
      ->condition('id', $commitmentId)
      ->execute()
      ->fetchField();
    return $ownerProject !== FALSE && (int) $ownerProject === $projectNid;
  }

}
