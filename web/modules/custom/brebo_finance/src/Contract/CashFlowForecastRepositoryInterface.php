<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for source-backed cash events and immutable forecasts. */
interface CashFlowForecastRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function cashEvent(string $sourceSystem, string $sourceType, string $sourceId, string $accountBucket): ?array;

  /** @param array<string,mixed> $fields */
  public function createCashEvent(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function updateCashEvent(int $eventId, array $fields): void;

  public function snapshotExists(int $projectNid, string $snapshotDate, string $scenario): bool;

  /** @param list<string> $statuses
   *  @return list<array<string,mixed>>
   */
  public function events(int $projectNid, string $endDate, array $statuses): array;

  /** @param array<string,mixed> $fields */
  public function createSnapshot(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

}
