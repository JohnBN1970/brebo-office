<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface ReceivablesWorkspaceReadRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function salesInvoiceDrafts(int $limit = 50): array;
  /** @return list<array<string,mixed>> */
  public function salesInvoices(int $limit = 50): array;
  /** @return list<int> */
  public function salesInvoiceIds(int $limit = 250): array;
}
