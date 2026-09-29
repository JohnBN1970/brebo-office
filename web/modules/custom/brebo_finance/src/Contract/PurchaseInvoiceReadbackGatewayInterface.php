<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PurchaseInvoiceReadbackGatewayInterface {
  /** @return list<array<string,mixed>> */
  public function all(): array;
}
