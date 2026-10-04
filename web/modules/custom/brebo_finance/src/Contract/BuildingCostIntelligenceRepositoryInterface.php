<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for verified building cost intelligence. */
interface BuildingCostIntelligenceRepositoryInterface {

  /** @param array<string,mixed> $fields */
  public function createObservation(array $fields): int;

  /** @return list<array<string,mixed>> */
  public function matchingObservations(
    string $costCode,
    string $workType,
    string $specificationHash,
    string $unit,
    string $region,
    string $snapshotDate,
  ): array;

  /** @param array<string,mixed> $fields */
  public function createBenchmark(array $fields): int;

  /** @return array<string,mixed>|null */
  public function benchmark(int $benchmarkId): ?array;

}
