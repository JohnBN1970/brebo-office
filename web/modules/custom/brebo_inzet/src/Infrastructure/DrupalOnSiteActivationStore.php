<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteActivationStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/** Drupal expirable KeyValue adapter for OnSite activation tokens. */
final class DrupalOnSiteActivationStore implements OnSiteActivationStoreInterface {

  public function __construct(private readonly KeyValueExpirableFactoryInterface $keyValueExpirable) {}

  public function set(string $tokenHash, array $record, int $ttl): void {
    $this->store()->setWithExpire($tokenHash, $record, $ttl);
  }

  public function get(string $tokenHash): ?array {
    $record = $this->store()->get($tokenHash);
    return is_array($record) ? $record : NULL;
  }

  public function delete(string $tokenHash): void {
    $this->store()->delete($tokenHash);
  }

  private function store() {
    return $this->keyValueExpirable->get('brebo_inzet.onsite_activation');
  }

}
