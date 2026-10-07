<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

interface IntegrationApiRuntimeConfigInterface {

  /** @return array{base_url:string,shared_secret:string}|null */
  public function configuration(): ?array;

}
