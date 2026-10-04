<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read source for the operational payables work queue. */
interface PayablesWorkQueueRepositoryInterface {

  public function purchaseInvoiceTableExists(): bool;

  /** @return list<array<string,mixed>> */
  public function openInvoices(): array;

}
