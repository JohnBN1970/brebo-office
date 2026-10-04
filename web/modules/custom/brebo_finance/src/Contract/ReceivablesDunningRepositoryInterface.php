<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Source and state boundary for receivables dunning. */
interface ReceivablesDunningRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function salesInvoice(int $salesInvoiceId): ?array;

  /** @return array<string,mixed> */
  public function state(int $salesInvoiceId): array;

  /** @param array<string,mixed> $state */
  public function saveState(int $salesInvoiceId, array $state): void;

}
