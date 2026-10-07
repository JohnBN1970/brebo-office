<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Infrastructure;

use Drupal\brebo_data_intake\Contract\IntakeLockInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Drupal lock adapter for intake concurrency control. */
final class DrupalIntakeLock implements IntakeLockInterface {

  public function __construct(private readonly LockBackendInterface $lock) {}

  public function acquire(string $name, float $timeout): bool {
    return $this->lock->acquire($name, $timeout);
  }

  public function wait(string $name, int $delay): bool {
    return $this->lock->wait($name, $delay);
  }

  public function release(string $name): void {
    $this->lock->release($name);
  }

}
