<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

final class OnSiteActivationManager {

  private const TTL = 604800;

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly OnSiteDeviceRegistry $deviceRegistry,
  ) {}

  /**
   * Creates a single-use activation token valid for seven days.
   */
  public function issue(int $uid): string {
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $this->store()->setWithExpire(hash('sha256', $token), [
      'uid' => $uid,
      'created' => time(),
    ], self::TTL);
    return $token;
  }

  /**
   * Consumes the activation token and returns a durable device token.
   */
  public function activate(string $token): ?string {
    $key = hash('sha256', trim($token));
    $record = $this->store()->get($key);
    if (!is_array($record) || empty($record['uid'])) {
      return NULL;
    }

    // Single use: consume before issuing the device credential.
    $this->store()->delete($key);
    return $this->deviceRegistry->issue((int) $record['uid']);
  }

  private function store() {
    return $this->keyValueExpirable->get('brebo_inzet.onsite_activation');
  }

}
