<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CollectionDebtorProfileStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal key-value adapter for collection debtor profiles. */
final class DrupalCollectionDebtorProfileStore implements CollectionDebtorProfileStoreInterface {

  private const STORE = 'brebo_finance.collection_debtor_profile';

  public function __construct(private readonly KeyValueFactoryInterface $keyValueFactory) {}

  public function get(int $organizationId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $organizationId, []);
    return is_array($value) ? $value : [];
  }

  public function save(int $organizationId, array $profile): void {
    $this->keyValueFactory->get(self::STORE)->set((string) $organizationId, $profile);
  }

}
