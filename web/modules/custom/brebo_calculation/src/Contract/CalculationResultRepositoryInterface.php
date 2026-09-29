<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Read boundary for canonical calculation result input.
 */
interface CalculationResultRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function version(int $calculationId, ?string $versionName = NULL): ?array;

  public function snapshotPayload(int $calculationId, string $version): ?string;

  /** @return list<array<string,mixed>> */
  public function rows(int $calculationId, string $version): array;

  /** @return list<array<string,mixed>> */
  public function recipeInstances(int $calculationId, string $version): array;

  /** @param list<int> $instanceIds @return list<array<string,mixed>> */
  public function recipeLines(array $instanceIds): array;

}
