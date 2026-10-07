<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationNumberStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal KeyValue adapter for administration numbering state. */
final class DrupalAdministrationNumberStore implements AdministrationNumberStoreInterface {

  private const COLLECTION = 'brebo_office_core.numbering';

  public function __construct(private readonly KeyValueFactoryInterface $keyValue) {}

  public function getReceipt(string $key): ?array {
    $value = $this->store()->get($key);
    return is_array($value) ? $value : NULL;
  }

  public function getInt(string $key, int $default): int {
    return (int) $this->store()->get($key, $default);
  }

  public function setReceipt(string $key, array $receipt): void {
    $this->store()->set($key, $receipt);
  }

  public function setInt(string $key, int $value): void {
    $this->store()->set($key, $value);
  }

  public function exists(string $key): bool {
    return $this->store()->get($key) !== NULL;
  }

  private function store() {
    return $this->keyValue->get(self::COLLECTION);
  }

}
