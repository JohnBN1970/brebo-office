<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for management decision records. */
interface ManagementDecisionRecordRepositoryInterface {

  public function ensureStorage(): void;

  /** @param array<string, mixed> $record */
  public function insert(array $record): int;

  /** @return array<int, array<string, mixed>> */
  public function findUnmeasured(): array;

  /** @param array<string, mixed> $fields */
  public function update(int $recordId, array $fields): void;

}
