<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationNormRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function activeNorms(string $domain, string $normKey): array;
}
