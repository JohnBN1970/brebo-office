<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Persistence boundary for calculation parameter versions.
 */
interface CalculationParametersRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function loadDraft(int $calculationId, string $version): ?array;

  /** @param array<string,mixed> $values */
  public function compareAndSwap(
    int $calculationId,
    string $version,
    string $expectedContentHash,
    array $values,
  ): bool;

}
