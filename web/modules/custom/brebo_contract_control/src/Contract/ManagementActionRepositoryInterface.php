<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for management actions. */
interface ManagementActionRepositoryInterface {

  /** @param array<string, mixed> $record */
  public function insert(array $record): int;

  /** @param array<string, mixed> $fields */
  public function resolveOpenAction(int $actionId, array $fields): void;

  /** @return array<int, array<string, mixed>> */
  public function findOpenActions(?string $actionKey = NULL): array;

  /** @return array<int, array<string, mixed>> */
  public function findOverdueActions(int $now): array;

  public function hasOpenAction(string $actionKey): bool;

}
