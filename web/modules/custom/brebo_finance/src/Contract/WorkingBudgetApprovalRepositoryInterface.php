<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface WorkingBudgetApprovalRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function budget(int $budgetId): ?array;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  /** @param array<string,mixed> $values */
  public function saveDecision(int $budgetId, string $discipline, array $values): void;

  /** @return array<string,string> */
  public function decisions(int $budgetId): array;

  public function hasInvalidLabourBaseline(int $budgetId): bool;

  /** @return list<array<string,mixed>> */
  public function baselineRows(int $budgetId): array;

  /** @param array<string,mixed> $values */
  public function updateBudget(int $budgetId, array $values): void;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
