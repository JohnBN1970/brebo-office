<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

interface PaymentBatchControlReadRepositoryInterface {

  public function supplierInvoiceStorageAvailable(): bool;

  /** @return array<string,mixed>|null */
  public function supplierInvoice(int $invoiceId): ?array;

}
