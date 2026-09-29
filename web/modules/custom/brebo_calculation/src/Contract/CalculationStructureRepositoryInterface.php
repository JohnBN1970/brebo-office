<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Persistence boundary for calculation structure commands.
 */
interface CalculationStructureRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function versionState(int $calculationId, string $version): ?array;

  /** @return array<string,mixed>|null */
  public function node(int $calculationId, string $version, string $nodeKey): ?array;

  public function nextSortOrder(int $calculationId, string $version, ?string $parentKey): int;

  /** @param array<string,mixed> $values */
  public function insert(array $values): void;

  public function reorder(int $calculationId, string $version, string $nodeKey, int $sortOrder): void;

}
