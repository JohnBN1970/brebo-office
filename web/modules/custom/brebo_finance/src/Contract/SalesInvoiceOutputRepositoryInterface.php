<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Source boundary for generated sales-invoice output. */
interface SalesInvoiceOutputRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function draft(int $draftId, bool $mustBeDraft): ?array;

  /** @return array<string,mixed> */
  public function draftContext(int $draftId): array;

}
