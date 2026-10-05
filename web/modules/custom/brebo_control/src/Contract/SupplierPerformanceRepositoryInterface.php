<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

interface SupplierPerformanceRepositoryInterface {

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

  /** @return array<int,array<string,mixed>> */
  public function eventsForSupplier(string $supplierName): array;

}
