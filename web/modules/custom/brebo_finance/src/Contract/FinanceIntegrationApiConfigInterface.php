<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinanceIntegrationApiConfigInterface {

  /** @return array{base_url:string,shared_secret:string} */
  public function configuration(): array;

}
