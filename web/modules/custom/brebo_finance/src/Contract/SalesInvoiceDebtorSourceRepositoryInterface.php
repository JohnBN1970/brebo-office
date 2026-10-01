<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Reads the canonical Finance linkage needed to resolve a sales-invoice debtor. */
interface SalesInvoiceDebtorSourceRepositoryInterface {

  /** @return array{invoice_number:string,draft_id:int,organization_id:int}|null */
  public function source(int $salesInvoiceId): ?array;

}
