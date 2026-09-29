<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Framework-neutral read boundary for calculation workspace state.
 */
interface CalculationWorkspaceReadRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function latestVersion(int $calculationId): ?array;

  /** @return array<int,array<string,mixed>> */
  public function structure(int $calculationId, string $version): array;

  /** @return array<int,array<string,mixed>> */
  public function rows(int $calculationId, string $version): array;

  /** @return array<int,array<string,mixed>> */
  public function recipes(int $calculationId, string $version): array;

  /** @return array<int,array<string,mixed>> */
  public function subcalculations(int $calculationId, string $version): array;

}
