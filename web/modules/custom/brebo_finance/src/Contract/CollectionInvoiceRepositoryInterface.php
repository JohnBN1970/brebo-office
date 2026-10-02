<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface CollectionInvoiceRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function get(int $invoiceId): ?array;

}
