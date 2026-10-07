<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface BusinessHealthCacheInterface {

  public function get(string $key): mixed;

  public function set(string $key, mixed $value, int $expiresAt, array $tags = []): void;

}
