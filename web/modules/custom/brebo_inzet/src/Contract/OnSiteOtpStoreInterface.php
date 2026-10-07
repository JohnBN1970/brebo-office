<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

interface OnSiteOtpStoreInterface {
  /** @param array<string, mixed> $record */
  public function set(string $challengeId, array $record, int $ttl): void;
  /** @return array<string, mixed>|null */
  public function get(string $challengeId): ?array;
  public function delete(string $challengeId): void;
}
