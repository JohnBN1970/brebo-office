<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for closed-loop control verification. */
interface ClosedLoopControlRepositoryInterface {

  /** @return array<int, array<string, mixed>> */
  public function findResolvedActionsBefore(int $cutoff): array;

  /** @param array<string, mixed> $fields */
  public function updateAction(int $actionId, array $fields): void;

}
