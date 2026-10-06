<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface CollectionProviderRuntimeConfigInterface {

  public function apiKey(): string;

  public function baseUrl(): string;

}
