<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationEstablishmentRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function version(int $calculationId, string $version): ?array;

  /** @return list<array<string,mixed>> */
  public function structure(int $calculationId, string $version): array;

  /** @return array<string,mixed>|null */
  public function rowDomain(int $rowId, string $version): ?array;

  public function snapshotExists(int $calculationId, string $version): bool;

  /** @param array<string,mixed> $snapshot @param array<string,mixed> $lock */
  public function persistSnapshotAndLock(
    int $calculationId,
    string $version,
    array $snapshot,
    array $lock,
  ): void;
}
