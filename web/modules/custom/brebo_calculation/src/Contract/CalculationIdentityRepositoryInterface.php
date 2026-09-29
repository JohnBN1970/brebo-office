<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationIdentityRepositoryInterface {
  public function structureIdentityExists(int $calculationId, string $version, int $candidateId): bool;
  public function rowIdentityExists(int $rowId): bool;
}
