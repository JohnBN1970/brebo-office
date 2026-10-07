<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

/** Lock boundary for source-neutral intake and human review decisions. */
interface IntakeLockInterface {

  public function acquire(string $name, float $timeout): bool;

  public function wait(string $name, int $delay): bool;

  public function release(string $name): void;

}
