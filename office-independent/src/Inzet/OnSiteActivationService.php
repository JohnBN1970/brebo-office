<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Office activation is intentionally disabled until a trusted, single-use
 * invitation store and verified Drupal UID -> Office person mapping exist.
 * Never infer identity from the token or create a device credential on guesswork.
 */
final class OnSiteActivationService {
  public function __construct(
    private PDO $db,
    private OnSiteDeviceTokenRepository $devices,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function activate(string $activationToken): array {
    if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $activationToken)) {
      return ['status'=>400,'body'=>['ok'=>false,'error'=>'invalid_activation_token']];
    }
    // The existing Drupal activation store is not the Office MySQL token store.
    // Until an atomic, verified bridge is implemented, no token may be consumed.
    return ['status'=>503,'body'=>['ok'=>false,'error'=>'activation_bridge_not_verified']];
  }
}
