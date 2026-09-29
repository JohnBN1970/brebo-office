<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationPriceSourceRepositoryInterface {
  public function isEditableVersion(int $calculationId, string $version): bool;
  public function rowExists(int $calculationId, string $version, int $rowId): bool;

  /** @param array<string,mixed> $source @param array<string,mixed> $line */
  public function createSourceWithLine(array $source, array $line): int;

  public function mappingId(int $sourceId, int $calculationId, string $version, int $rowId): ?int;

  /** @param array<string,mixed> $approval */
  public function approveSource(
    int $calculationId,
    string $version,
    int $rowId,
    int $sourceId,
    int $mappingId,
    string $costCarrier,
    string $targetField,
    float $unitCost,
    array $approval,
  ): void;
}
