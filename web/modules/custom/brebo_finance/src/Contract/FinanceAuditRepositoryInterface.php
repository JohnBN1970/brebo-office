<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Writes Finance audit provenance without exposing Drupal storage. */
interface FinanceAuditRepositoryInterface {

  public function available(): bool;

  /** @param array<string,mixed> $fields */
  public function append(array $fields): void;

}
