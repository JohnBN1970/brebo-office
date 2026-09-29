<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface BudgetMutationRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function lockedWorkingBudget(int $budgetId): ?array;

  /** @return array<string,mixed>|null */
  public function editableMutation(int $mutationId): ?array;

  public function budgetLineBelongsToBudget(int $lineId, int $budgetId): bool;

  public function mutationHasLines(int $mutationId): bool;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  /** @param array<string,mixed> $values */
  public function insertMutation(array $values): int;

  /** @param array<string,mixed> $values */
  public function insertMutationLine(array $values): int;

  /** @return array<string,mixed> */
  public function mutationTotals(int $mutationId): array;

  /** @param array<string,mixed> $values */
  public function updateMutation(int $mutationId, array $values): void;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
