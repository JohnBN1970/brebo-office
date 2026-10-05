<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for collection-provider transfer state. */
interface CollectionTransferRepositoryInterface {

  /** @return array<string,mixed> */
  public function get(int $salesInvoiceId): array;

  /** @param array<string,mixed> $state */
  public function save(int $salesInvoiceId, array $state): void;

}
