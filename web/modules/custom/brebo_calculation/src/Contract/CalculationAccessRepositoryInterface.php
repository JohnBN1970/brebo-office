<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationAccessRepositoryInterface {
  public function calculationExists(int $calculationId): bool;
  public function latestEstablishedVersion(int $calculationId): ?string;
}
