<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read model boundary for organisation-wide cashflow management. */
interface CashFlowManagementRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function salesInvoices(): array;

  /** @return list<array<string,mixed>> */
  public function overdueSalesInvoices(string $asOfDate): array;

  /** @return list<array<string,mixed>> */
  public function cashEventsUntil(string $endDate): array;

}
