<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Persistence boundary for administration document numbering. */
interface AdministrationNumberStoreInterface {

  /** @return array<string, mixed>|null */
  public function getReceipt(string $key): ?array;

  public function getInt(string $key, int $default): int;

  /** @param array<string, mixed> $receipt */
  public function setReceipt(string $key, array $receipt): void;

  public function setInt(string $key, int $value): void;

  public function exists(string $key): bool;

}
