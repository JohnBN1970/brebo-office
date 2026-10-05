<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for sales-invoice number reservations and cursors. */
interface SalesInvoiceNumberStoreInterface {

  public function has(string $key): bool;

  public function get(string $key, mixed $default = NULL): mixed;

  public function set(string $key, mixed $value): void;

}
