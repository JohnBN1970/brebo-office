<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Calculates a privacy-minimal point-to-building geofence result.
 */
final class WorkforceGeoFence {

  public const DEFAULT_RADIUS_METERS = 150.0;

  /**
   * @return array{distance: float, configured_radius: float, effective_radius: float, status: string}
   */
  public function assess(
    ?float $buildingLatitude,
    ?float $buildingLongitude,
    ?float $clockLatitude,
    ?float $clockLongitude,
    ?float $configuredRadius = NULL,
    ?float $accuracy = NULL,
  ): array {
    $radius = $this->normalizeRadius($configuredRadius);
    $effectiveRadius = $radius + max(0.0, (float) $accuracy);

    if ($buildingLatitude === NULL || $buildingLongitude === NULL
      || $clockLatitude === NULL || $clockLongitude === NULL) {
      return [
        'distance' => 0.0,
        'configured_radius' => $radius,
        'effective_radius' => $effectiveRadius,
        'status' => 'Geen locatie',
      ];
    }

    $earthRadius = 6371000.0;
    $lat1 = deg2rad($buildingLatitude);
    $lat2 = deg2rad($clockLatitude);
    $deltaLat = deg2rad($clockLatitude - $buildingLatitude);
    $deltaLon = deg2rad($clockLongitude - $buildingLongitude);
    $a = sin($deltaLat / 2) ** 2
      + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));

    return [
      'distance' => round($distance, 2),
      'configured_radius' => $radius,
      'effective_radius' => round($effectiveRadius, 2),
      'status' => $distance <= $effectiveRadius ? 'Binnen zone' : 'Buiten zone',
    ];
  }

  /**
   * Resolves the radius in this order: shift override, building, default.
   */
  public function resolveRadius(?float $shiftRadius, ?float $buildingRadius): float {
    if ($shiftRadius !== NULL && $shiftRadius > 0) {
      return $this->normalizeRadius($shiftRadius);
    }
    if ($buildingRadius !== NULL && $buildingRadius > 0) {
      return $this->normalizeRadius($buildingRadius);
    }
    return self::DEFAULT_RADIUS_METERS;
  }

  private function normalizeRadius(?float $radius): float {
    if ($radius === NULL || $radius <= 0) {
      return self::DEFAULT_RADIUS_METERS;
    }

    // Prevent accidental unusable or effectively unrestricted work zones.
    return min(5000.0, max(10.0, $radius));
  }

}
