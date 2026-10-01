<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Reads supplier references from the Finance purchase-invoice store. */
interface PurchaseInvoiceSupplierSourceRepositoryInterface {

  /**
   * @return array{invoice_count:int,suppliers:list<array{contact_id:string,name:string}>}
   */
  public function snapshot(): array;

}
