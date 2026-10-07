<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalcIntegrationRuntimeInterface {

  public function sharedSecret(): string;

  public function has(string $key): bool;

  public function remember(string $key, int $expiresAt): void;

}
