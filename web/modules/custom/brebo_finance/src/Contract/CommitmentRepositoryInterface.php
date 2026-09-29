<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface CommitmentRepositoryInterface {

  public function hasLockedWorkingBudget(int $projectNid): bool;

  /** @return array<string,mixed>|null */
  public function draftCommitment(int $commitmentId): ?array;

  /** @return array<string,mixed>|null */
  public function lockedBudgetLine(int $budgetLineId, int $projectNid): ?array;

  public function committedAmount(int $budgetLineId): string;

  public function approvedBudgetAdjustment(int $budgetLineId): string;

  public function nextLineNumber(int $commitmentId): int;

  /** @return array<string,mixed> */
  public function commitmentTotals(int $commitmentId): array;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  /** @param array<string,mixed> $values */
  public function insertCommitment(array $values): int;

  public function commitmentNumber(int $commitmentId): ?string;

  /** @param array<string,mixed> $values */
  public function updateCommitment(int $commitmentId, array $values): int;

  public function deleteCommitmentWithNumber(int $commitmentId, string $number): void;

  /** @param array<string,mixed> $values */
  public function insertLine(array $values): int;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
