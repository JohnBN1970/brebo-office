<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for controlled project change orders. */
interface ChangeOrderRepositoryInterface {

  public function approvedContractExists(int $projectNid, int $contractId): bool;

  /** @param array<string,mixed> $fields */
  public function createChange(array $fields): int;

  /** @return array<string,mixed>|null */
  public function getChange(int $changeId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateChange(int $changeId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function createRevenueMutation(array $fields): void;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

  public function atomic(callable $operation): mixed;

}
