<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

interface ControlNotificationRepositoryInterface {

  public function existsByDedupKey(string $dedupKey): bool;

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

}
