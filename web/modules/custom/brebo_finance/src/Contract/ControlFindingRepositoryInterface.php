<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for financial control findings and their audit trail. */
interface ControlFindingRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function get(int $findingId): ?array;

  /** @param array<string,mixed> $fields */
  public function update(int $findingId, array $fields): void;

  /** @param array<string,mixed> $audit */
  public function appendAudit(array $audit): void;

}
