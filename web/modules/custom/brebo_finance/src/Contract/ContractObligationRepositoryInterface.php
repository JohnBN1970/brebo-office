<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for contractual obligations and audit trail. */
interface ContractObligationRepositoryInterface {

  public function approvedContractExists(int $projectNid, int $contractId): bool;

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

  /** @return array<string,mixed>|null */
  public function get(int $obligationId): ?array;

  /** @param array<string,mixed> $fields */
  public function update(int $obligationId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

}
