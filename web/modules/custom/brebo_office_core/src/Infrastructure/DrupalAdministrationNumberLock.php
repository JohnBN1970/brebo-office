<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationNumberLockInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Drupal lock adapter for administration numbering. */
final class DrupalAdministrationNumberLock implements AdministrationNumberLockInterface {

  public function __construct(private readonly LockBackendInterface $lock) {}

  public function acquire(string $name, float $timeout): bool {
    return $this->lock->acquire($name, $timeout);
  }

  public function release(string $name): void {
    $this->lock->release($name);
  }

}
