<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Runtime source for BREBO debtor bank settings used by SEPA export. */
interface SepaDebtorSettingsInterface {

  /** @return array{name:string,iban:string,bic:string} */
  public function debtor(): array;

}
