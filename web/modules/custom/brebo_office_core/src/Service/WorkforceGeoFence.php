<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Calculates privacy-minimal point-to-building geofence results.
 */
final class WorkforceGeoFence {

  /**
   * @return array{distance: float, status: string}
   */
  public function assess(
    ?float $buildingLatitude,
    ?float $buildingLongitude,
    ?float $clockLatitude,
    ?float $clockLongitude,
    float $radius = 150.0,
    ?float $accuracy = NULL,
  ): array {
    if ($buildingLatitude === NULL || $buildingLongitude === NULL
      || $clockLatitude === NULL || $clockLongitude === NULL) {
      return ['distance' => 0.0, 'status' => 'Geen locatie'];
    }

    $earthRadius = 6371000.0;
    $lat1 = deg2rad($buildingLatitude);
    $lat2 = deg2rad($clockLatitude);
    $deltaLat = deg2rad($clockLatitude - $buildingLatitude);
    $deltaLon = deg2rad($clockLongitude - $buildingLongitude);
    $a = sin($deltaLat / 2) ** 2
      + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    $effectiveRadius = max(0.0, $radius) + max(0.0, (float) $accuracy);

    return [
      'distance' => round($distance, 2),
      'status' => $distance <= $effectiveRadius ? 'Binnen zone' : 'Buiten zone',
    ];
  }

}
