<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface SepaBankAccountConfigInterface {

  /** @return array{name:string,iban:string,bic:string} */
  public function debtorAccount(): array;

}
