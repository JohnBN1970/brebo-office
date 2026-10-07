<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for management trend snapshots. */
interface ManagementTrendRepositoryInterface {

  public function ensureStorage(): void;

  /** @return array<string, mixed>|null */
  public function findLatestSnapshot(int $start, int $end): ?array;

  /** @param array<string, mixed> $record */
  public function upsertSnapshot(string $periodKey, array $record): void;

}
