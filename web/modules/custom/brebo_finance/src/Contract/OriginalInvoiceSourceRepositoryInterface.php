<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface OriginalInvoiceSourceRepositoryInterface {

  public function available(): bool;

  /** @return list<string> */
  public function payloads(int $invoiceId): array;

}
