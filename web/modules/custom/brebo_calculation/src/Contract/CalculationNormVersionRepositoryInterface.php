<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationNormVersionRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function norm(int $normId): ?array;

  /** @param array<string,mixed> $replacement */
  public function replace(int $normId, array $replacement): int;
}
