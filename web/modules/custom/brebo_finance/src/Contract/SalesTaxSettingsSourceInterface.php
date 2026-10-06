<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Configuration boundary for Finance sales tax and G-account settings. */
interface SalesTaxSettingsSourceInterface {

  /** @return array<string,mixed> */
  public function salesSettings(): array;

}
