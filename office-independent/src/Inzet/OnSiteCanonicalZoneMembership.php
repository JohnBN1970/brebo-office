<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Canonical zone snapshot contract from existing brebo_clock_zone records.
 * A trusted server-side synchronizer supplies these records, never the device.
 * No GPS coordinates or raw location history are needed for authorization.
 */
final class OnSiteCanonicalZoneMembership {
  /** @param array<int|string,array{project_id:string,active:bool}> $verifiedZonesById */
  public function __construct(private array $verifiedZonesById) {
    foreach ($verifiedZonesById as $zoneId => $zone) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D', (string)$zoneId)
        || !isset($zone['project_id'],$zone['active'])
        || !is_string($zone['project_id']) || !is_bool($zone['active'])) {
        throw new InvalidArgumentException('Invalid canonical zone snapshot');
      }
    }
  }

  public function isMember(string $projectId, string $zoneId): bool {
    $zone = $this->verifiedZonesById[$zoneId] ?? null;
    return $zone !== null && $zone['active'] === true
      && hash_equals($zone['project_id'], $projectId);
  }

  public function callback(): \Closure {
    return fn(string $projectId,string $zoneId): bool => $this->isMember($projectId,$zoneId);
  }
}
