<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationDraftRepositoryInterface {
  public function latestVersion(int $calculationId): ?string;

  /** @param array<string,mixed> $values */
  public function insertVersion(array $values): void;
}
