<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationObjectLineRepositoryInterface {
  /** @param array<string,mixed> $values */
  public function updateObjectRow(int $calculationId, string $version, int $rowId, array $values): void;
}
