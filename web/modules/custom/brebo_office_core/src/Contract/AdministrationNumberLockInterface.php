<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Lock boundary for administration document numbering. */
interface AdministrationNumberLockInterface {

  public function acquire(string $name, float $timeout): bool;

  public function release(string $name): void;

}
