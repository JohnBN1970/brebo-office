<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface WorkingBudgetImportRepositoryInterface {

  public function isBreboProject(int $projectNid): bool;

  /** @return array<string,mixed>|null */
  public function calculationVersion(int $calculationId, string $calculationVersion): ?array;

  /** @return array<string,mixed>|null */
  public function calculationSnapshot(int $calculationId, string $calculationVersion): ?array;

  public function workingBudgetExists(int $projectNid, int $calculationId, string $calculationVersion): bool;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  /** @param array<string,mixed> $values */
  public function insertBudget(array $values): int;

  /** @param array<string,mixed> $values */
  public function insertBudgetLine(array $values): int;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
