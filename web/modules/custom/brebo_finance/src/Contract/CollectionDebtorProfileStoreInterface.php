<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for structured collection debtor profiles. */
interface CollectionDebtorProfileStoreInterface {

  /** @return array<string,mixed> */
  public function get(int $organizationId): array;

  /** @param array<string,mixed> $profile */
  public function save(int $organizationId, array $profile): void;

}
