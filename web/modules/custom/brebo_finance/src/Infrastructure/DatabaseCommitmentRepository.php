<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CommitmentRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCommitmentRepository implements CommitmentRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function hasLockedWorkingBudget(int $projectNid): bool {
    return (bool) $this->database->select('brebo_finance_budget', 'b')->condition('project_nid', $projectNid)->condition('budget_type', 'working')->condition('status', 'locked')->countQuery()->execute()->fetchField();
  }

  public function draftCommitment(int $commitmentId): ?array {
    $row = $this->database->select('brebo_finance_commitment', 'c')->fields('c')->condition('id', $commitmentId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function lockedBudgetLine(int $budgetLineId, int $projectNid): ?array {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $row = $query->fields('l')->condition('l.id', $budgetLineId)->condition('b.project_nid', $projectNid)->condition('b.budget_type', 'working')->condition('b.status', 'locked')->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function committedAmount(int $budgetLineId): string {
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $query->condition('l.budget_line_id', $budgetLineId)->condition('c.status', ['cancelled'], 'NOT IN')->addExpression('COALESCE(SUM(l.amount_ex_vat), 0)', 'committed_total');
    return (string) $query->execute()->fetchField();
  }

  public function approvedBudgetAdjustment(int $budgetLineId): string {
    $query = $this->database->select('brebo_finance_budget_mutation_line', 'ml');
    $query->join('brebo_finance_budget_mutation', 'm', 'm.id = ml.mutation_id');
    $query->condition('ml.budget_line_id', $budgetLineId)->condition('m.status', 'approved')->addExpression('COALESCE(SUM(ml.adjustment_ex_vat), 0)', 'approved_adjustment');
    return (string) $query->execute()->fetchField();
  }

  public function nextLineNumber(int $commitmentId): int {
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->condition('commitment_id', $commitmentId)->addExpression('COALESCE(MAX(line_number), 0) + 1', 'next_line');
    return (int) $query->execute()->fetchField();
  }

  public function commitmentTotals(int $commitmentId): array {
    $query = $this->database->select('brebo_finance_commitment_line', 'l');
    $query->condition('commitment_id', $commitmentId)->addExpression('COALESCE(SUM(amount_ex_vat), 0)', 'amount_ex_vat')->addExpression('COALESCE(SUM(vat_amount), 0)', 'vat_amount')->addExpression('COALESCE(SUM(amount_inc_vat), 0)', 'amount_inc_vat');
    return $query->execute()->fetchAssoc() ?: ['amount_ex_vat'=>'0','vat_amount'=>'0','amount_inc_vat'=>'0'];
  }

  public function transactional(callable $callback): mixed {
    $transaction = $this->database->startTransaction();
    try { return $callback(); }
    catch (\Throwable $exception) { $transaction->rollBack(); throw $exception; }
  }

  public function insertCommitment(array $values): int { return (int) $this->database->insert('brebo_finance_commitment')->fields($values)->execute(); }

  public function commitmentNumber(int $commitmentId): ?string {
    $value = $this->database->select('brebo_finance_commitment', 'c')->fields('c', ['commitment_number'])->condition('id', $commitmentId)->execute()->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

  public function updateCommitment(int $commitmentId, array $values): int {
    return (int) $this->database->update('brebo_finance_commitment')->fields($values)->condition('id', $commitmentId)->execute();
  }

  public function deleteCommitmentWithNumber(int $commitmentId, string $number): void {
    $this->database->delete('brebo_finance_commitment')->condition('id', $commitmentId)->condition('commitment_number', $number)->execute();
  }

  public function insertLine(array $values): int { return (int) $this->database->insert('brebo_finance_commitment_line')->fields($values)->execute(); }

  public function insertAudit(array $values): void { $this->database->insert('brebo_finance_audit')->fields($values)->execute(); }

}
