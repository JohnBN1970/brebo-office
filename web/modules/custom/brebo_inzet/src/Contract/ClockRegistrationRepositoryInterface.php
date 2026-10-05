<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Persistence boundary for normalized clock registrations. */
interface ClockRegistrationRepositoryInterface {

  /**
   * @param array<string,mixed> $fields
   */
  public function create(array $fields): int;

}
