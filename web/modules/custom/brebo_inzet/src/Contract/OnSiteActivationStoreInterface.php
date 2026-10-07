<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Storage boundary for single-use OnSite activation tokens. */
interface OnSiteActivationStoreInterface {

  /** @param array<string, mixed> $record */
  public function set(string $tokenHash, array $record, int $ttl): void;

  /** @return array<string, mixed>|null */
  public function get(string $tokenHash): ?array;

  public function delete(string $tokenHash): void;

}
