<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

final class OnSiteDeviceRegistry {

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * Registers an OnSite installation and returns its opaque bearer token.
   */
  public function issue(int $uid): string {
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $this->store()->set(hash('sha256', $token), [
      'uid' => $uid,
      'created' => time(),
    ]);
    return $token;
  }

  public function resolve(string $token): ?int {
    $token = trim($token);
    if ($token === '') {
      return NULL;
    }
    $record = $this->store()->get(hash('sha256', $token));
    if (!is_array($record) || empty($record['uid'])) {
      return NULL;
    }
    return (int) $record['uid'];
  }

  public function revoke(string $token): void {
    $token = trim($token);
    if ($token !== '') {
      $this->store()->delete(hash('sha256', $token));
    }
  }

  private function store() {
    return $this->keyValue->get('brebo_inzet.onsite_devices');
  }

}
