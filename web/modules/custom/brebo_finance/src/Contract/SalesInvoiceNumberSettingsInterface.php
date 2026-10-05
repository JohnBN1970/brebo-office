<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Configuration boundary for BREBO sales-invoice numbering. */
interface SalesInvoiceNumberSettingsInterface {

  /** @return array{prefix:string,separator:string,digits:int,include_year:bool,start_number:int,reset_yearly:bool} */
  public function settings(): array;

}
