<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Contract;

interface GlassAvailabilityRepositoryInterface {

  public function isAvailable(): bool;

  /** @param array<string,mixed> $values */
  public function record(array $values): int;

  /** @return array<string,float> */
  public function totals(int $projectId, string $glassGroupKey): array;

}
