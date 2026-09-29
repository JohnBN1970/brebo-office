<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface KozijnPriceObservationStoreInterface {
  /** @param array<string,mixed> $values */
  public function insert(array $values): int;

  /** @return array<int,array<string,mixed>> */
  public function approved(string $system, string $type, int $fields = 1): array;
}
