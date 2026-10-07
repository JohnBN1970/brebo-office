<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteDeviceStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal KeyValue adapter for durable OnSite device credentials. */
final class DrupalOnSiteDeviceStore implements OnSiteDeviceStoreInterface {

  public function __construct(private readonly KeyValueFactoryInterface $keyValue) {}

  public function set(string $tokenHash, array $record): void {
    $this->store()->set($tokenHash, $record);
  }

  public function get(string $tokenHash): ?array {
    $record = $this->store()->get($tokenHash);
    return is_array($record) ? $record : NULL;
  }

  public function delete(string $tokenHash): void {
    $this->store()->delete($tokenHash);
  }

  private function store() {
    return $this->keyValue->get('brebo_inzet.onsite_devices');
  }

}
