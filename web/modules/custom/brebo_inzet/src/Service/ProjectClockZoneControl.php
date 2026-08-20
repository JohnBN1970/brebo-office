<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Resolves clocking against one or more active project work zones.
 */
final class ProjectClockZoneControl {

  public function __construct(private readonly WorkforceGeoFence $geoFence) {}

  /**
   * @param array<int, array{id?: mixed, name?: mixed, latitude?: mixed, longitude?: mixed, radius?: mixed, active?: mixed}> $zones
   *
   * @return array{status: string, matched_zone_id: mixed, matched_zone_name: ?string, distance: float, configured_radius: float, results: array<int, array<string, mixed>>}
   */
  public function assess(array $zones, ?float $clockLatitude, ?float $clockLongitude, ?float $accuracy = NULL): array {
    $results = [];
    $best = NULL;

    foreach ($zones as $zone) {
      if (array_key_exists('active', $zone) && !$zone['active']) {
        continue;
      }

      $result = $this->geoFence->assess(
        isset($zone['latitude']) ? (float) $zone['latitude'] : NULL,
        isset($zone['longitude']) ? (float) $zone['longitude'] : NULL,
        $clockLatitude,
        $clockLongitude,
        isset($zone['radius']) ? (float) $zone['radius'] : NULL,
        $accuracy,
      );
      $result['zone_id'] = $zone['id'] ?? NULL;
      $result['zone_name'] = isset($zone['name']) ? (string) $zone['name'] : NULL;
      $results[] = $result;

      if ($result['status'] === 'Binnen zone' && ($best === NULL || $result['distance'] < $best['distance'])) {
        $best = $result;
      }
    }

    if ($best !== NULL) {
      return [
        'status' => 'Binnen zone',
        'matched_zone_id' => $best['zone_id'],
        'matched_zone_name' => $best['zone_name'],
        'distance' => $best['distance'],
        'configured_radius' => $best['configured_radius'],
        'results' => $results,
      ];
    }

    if ($results === []) {
      return [
        'status' => 'Geen werkzone',
        'matched_zone_id' => NULL,
        'matched_zone_name' => NULL,
        'distance' => 0.0,
        'configured_radius' => 0.0,
        'results' => [],
      ];
    }

    $nearest = $results[0];
    foreach ($results as $result) {
      if ($result['distance'] < $nearest['distance']) {
        $nearest = $result;
      }
    }

    return [
      'status' => $nearest['status'] === 'Geen locatie' ? 'Geen locatie' : 'Buiten zone',
      'matched_zone_id' => NULL,
      'matched_zone_name' => NULL,
      'distance' => $nearest['distance'],
      'configured_radius' => $nearest['configured_radius'],
      'results' => $results,
    ];
  }

}
