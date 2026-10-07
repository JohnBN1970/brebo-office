<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface DocumentExtractionRuntimeConfigInterface {

  public function endpoint(): string;

  public function token(): string;

}
