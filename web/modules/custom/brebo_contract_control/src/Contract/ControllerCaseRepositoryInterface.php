<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for controller-case management. */
interface ControllerCaseRepositoryInterface {

  /** @param array<string, mixed> $record */
  public function insert(array $record): int;

  /** @return array<string, mixed>|null */
  public function findById(int $caseId): ?array;

  /** @param array<string, mixed> $fields */
  public function update(int $caseId, array $fields): void;

}
