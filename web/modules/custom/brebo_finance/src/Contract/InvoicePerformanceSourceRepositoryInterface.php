<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface InvoicePerformanceSourceRepositoryInterface {

  public function invoiceLineTableExists(): bool;

  /** @return array<string,mixed>|null */
  public function invoiceLine(int $invoiceLineId): ?array;

  public function performanceTableExists(): bool;

  /** @return list<array<string,mixed>> */
  public function performances(int $commitmentLineId): array;

}
