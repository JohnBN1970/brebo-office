<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Storage boundary for receivables reconciliation health state. */
interface ReceivablesReconciliationStateStoreInterface {

  /** @return array<string, mixed> */
  public function get(): array;

  /** @param array<string, mixed> $value */
  public function set(array $value): void;

}
