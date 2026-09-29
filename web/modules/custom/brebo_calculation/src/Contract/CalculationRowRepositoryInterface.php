<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Persistence boundary for calculation row commands.
 */
interface CalculationRowRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function versionState(int $calculationId, string $version): ?array;

  /** @return array<string,mixed>|null */
  public function row(int $calculationId, string $version, int $rowId): ?array;

  /** @return array<string,mixed>|null */
  public function structureNode(int $calculationId, string $version, string $nodeKey): ?array;

  public function structureChildCount(int $calculationId, string $version, string $parentKey): int;

  public function nextSortOrder(int $calculationId, string $version, string $paragraphKey): int;

  /** @param array<string,mixed> $values */
  public function insert(array $values): void;

  /** @param array<string,mixed> $values */
  public function update(int $calculationId, string $version, int $rowId, array $values): void;

  public function delete(int $calculationId, string $version, int $rowId): void;

}
