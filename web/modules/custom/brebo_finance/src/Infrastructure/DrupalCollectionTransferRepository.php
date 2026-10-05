<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CollectionTransferRepositoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal key-value adapter for collection transfer state. */
final class DrupalCollectionTransferRepository implements CollectionTransferRepositoryInterface {

  private const STORE = 'brebo_finance.collection_transfer';

  public function __construct(private readonly KeyValueFactoryInterface $keyValueFactory) {}

  public function get(int $salesInvoiceId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $salesInvoiceId, []);
    return is_array($value) ? $value : [];
  }

  public function save(int $salesInvoiceId, array $state): void {
    $this->keyValueFactory->get(self::STORE)->set((string) $salesInvoiceId, $state);
  }

}
