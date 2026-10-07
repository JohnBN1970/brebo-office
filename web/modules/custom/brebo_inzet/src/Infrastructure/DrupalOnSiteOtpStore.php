<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteOtpStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

final class DrupalOnSiteOtpStore implements OnSiteOtpStoreInterface {
  public function __construct(private readonly KeyValueExpirableFactoryInterface $keyValueExpirable) {}
  public function set(string $challengeId, array $record, int $ttl): void {
    $this->store()->setWithExpire($challengeId, $record, $ttl);
  }
  public function get(string $challengeId): ?array {
    $record = $this->store()->get($challengeId);
    return is_array($record) ? $record : NULL;
  }
  public function delete(string $challengeId): void {
    $this->store()->delete($challengeId);
  }
  private function store() {
    return $this->keyValueExpirable->get('brebo_inzet.onsite_otp');
  }
}
