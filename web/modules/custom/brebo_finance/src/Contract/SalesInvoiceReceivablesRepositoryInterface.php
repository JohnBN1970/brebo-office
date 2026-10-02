<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface SalesInvoiceReceivablesRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function byMoneybirdId(string $moneybirdId): ?array;
}
