<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Storage boundary for durable OnSite device credentials. */
interface OnSiteDeviceStoreInterface {

  /** @param array<string, mixed> $record */
  public function set(string $tokenHash, array $record): void;

  /** @return array<string, mixed>|null */
  public function get(string $tokenHash): ?array;

  public function delete(string $tokenHash): void;

}
