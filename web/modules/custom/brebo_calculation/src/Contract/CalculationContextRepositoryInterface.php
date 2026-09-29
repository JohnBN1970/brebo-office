<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationContextRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function get(int $calculationId): ?array;

  /** @param array<string,mixed> $context */
  public function upsert(int $calculationId, array $context): void;
}
