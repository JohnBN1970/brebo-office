<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalcIntegrationRuntimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;

final class DrupalCalcIntegrationRuntime implements CalcIntegrationRuntimeInterface {

  public function __construct(private readonly CacheBackendInterface $cache) {}

  public function sharedSecret(): string {
    return trim((string) (getenv('BREBO_CALC_SHARED_SECRET') ?: Settings::get('brebo_calc_shared_secret', '')));
  }

  public function has(string $key): bool {
    return (bool) $this->cache->get($key);
  }

  public function remember(string $key, int $expiresAt): void {
    $this->cache->set($key, TRUE, $expiresAt);
  }

}
