<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Concurrency boundary for sales-invoice numbering. */
interface SalesInvoiceNumberLockInterface {

  public function acquire(float $timeout = 10.0): bool;

  public function release(): void;

}
