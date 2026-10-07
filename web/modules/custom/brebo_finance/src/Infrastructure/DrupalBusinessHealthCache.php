<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BusinessHealthCacheInterface;
use Drupal\Core\Cache\CacheBackendInterface;

final class DrupalBusinessHealthCache implements BusinessHealthCacheInterface {

  public function __construct(private readonly CacheBackendInterface $cache) {}

  public function get(string $key): mixed {
    $cached = $this->cache->get($key);
    return $cached ? $cached->data : NULL;
  }

  public function set(string $key, mixed $value, int $expiresAt, array $tags = []): void {
    $this->cache->set($key, $value, $expiresAt, $tags);
  }

}
