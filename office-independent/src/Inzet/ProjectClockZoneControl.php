<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Framework-independent multi-zone matching, without storage or clock writes.
 */
final class ProjectClockZoneControl {
  public function __construct(private readonly WorkforceGeoFence $geoFence) {}

  /** @param array<int,array<string,mixed>> $zones */
  public function assess(array $zones, ?float $latitude, ?float $longitude, ?float $accuracy = null): array {
    $results = [];
    $best = null;
    foreach ($zones as $zone) {
      if (array_key_exists('active', $zone) && !$zone['active']) continue;
      $result = $this->geoFence->assess(
        isset($zone['latitude']) ? (float) $zone['latitude'] : null,
        isset($zone['longitude']) ? (float) $zone['longitude'] : null,
        $latitude, $longitude,
        isset($zone['radius']) ? (float) $zone['radius'] : null,
        $accuracy
      );
      $result['zone_id'] = $zone['id'] ?? null;
      $result['zone_name'] = isset($zone['name']) ? (string) $zone['name'] : null;
      $results[] = $result;
      if ($result['status'] === 'Binnen zone' && ($best === null || $result['distance'] < $best['distance'])) $best = $result;
    }
    if ($best !== null) return ['status' => 'Binnen zone', 'matched_zone_id' => $best['zone_id'], 'matched_zone_name' => $best['zone_name'], 'distance' => $best['distance'], 'configured_radius' => $best['configured_radius'], 'results' => $results];
    if ($results === []) return ['status' => 'Geen werkzone', 'matched_zone_id' => null, 'matched_zone_name' => null, 'distance' => 0.0, 'configured_radius' => 0.0, 'results' => []];
    usort($results, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
    $nearest = $results[0];
    return ['status' => $nearest['status'] === 'Geen locatie' ? 'Geen locatie' : 'Buiten zone', 'matched_zone_id' => null, 'matched_zone_name' => null, 'distance' => $nearest['distance'], 'configured_radius' => $nearest['configured_radius'], 'results' => $results];
  }
}
