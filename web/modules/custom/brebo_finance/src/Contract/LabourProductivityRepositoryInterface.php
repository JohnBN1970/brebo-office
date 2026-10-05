<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for labour budgets, entries and productivity analysis. */
interface LabourProductivityRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function budgetLine(int $budgetLineId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateBudgetLine(int $budgetLineId, array $fields): void;

  /** @return array<string,mixed>|null */
  public function labourEntryBySource(string $sourceSystem, string $sourceRecordId): ?array;

  /** @param array<string,mixed> $fields */
  public function createLabourEntry(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function updateLabourEntry(int $entryId, array $fields): void;

  /** @return array<string,mixed>|null */
  public function labourEntry(int $entryId): ?array;

  /** @return list<array<string,mixed>> */
  public function lockedLabourLines(int $projectNid): array;

  /** @return array<int,array{status:string,actual_hours:string,changed:int}> */
  public function inzetActualStatuses(int $projectNid): array;

  /** @return array{status:string,actual_hours:string,changed:int}|null */
  public function inzetActualStatus(int $projectNid, int $assignmentNid): ?array;

  /** @param list<string> $statuses */
  public function sumHours(int $budgetLineId, string $field, array $statuses): string;

  /** @param list<string> $statuses */
  public function sumCost(int $budgetLineId, array $statuses, string $sourceSystem): string;

  /** @param list<string> $statuses */
  public function maxProgress(int $budgetLineId, array $statuses): ?string;

  public function countUnlinked(int $projectNid): int;

  /** @param array<string,mixed> $audit */
  public function appendAudit(array $audit): void;

}
