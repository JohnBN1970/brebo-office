<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationReadinessRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function rows(int $calculationId, string $version): array;

  /** @return list<array<string,mixed>> */
  public function recipeLines(int $calculationId, string $version): array;
}
