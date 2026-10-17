<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Framework-independent geofence calculation reused from BREBO Inzet.
 * Does not persist employee positions or trigger attendance registration.
 */
final class WorkforceGeoFence {
  public const DEFAULT_RADIUS_METERS = 150.0;

  /** @return array{distance:float,configured_radius:float,effective_radius:float,status:string} */
  public function assess(?float $buildingLatitude, ?float $buildingLongitude, ?float $clockLatitude, ?float $clockLongitude, ?float $configuredRadius = null, ?float $accuracy = null): array {
    $radius = $this->normalizeRadius($configuredRadius);
    $effectiveRadius = $radius + max(0.0, (float) $accuracy);
    if ($buildingLatitude === null || $buildingLongitude === null || $clockLatitude === null || $clockLongitude === null) {
      return ['distance' => 0.0, 'configured_radius' => $radius, 'effective_radius' => $effectiveRadius, 'status' => 'Geen locatie'];
    }
    foreach ([$buildingLatitude, $clockLatitude] as $latitude) {
      if (!is_finite($latitude) || abs($latitude) > 90) {
        return ['distance' => 0.0, 'configured_radius' => $radius, 'effective_radius' => $effectiveRadius, 'status' => 'Geen locatie'];
      }
    }
    foreach ([$buildingLongitude, $clockLongitude] as $longitude) {
      if (!is_finite($longitude) || abs($longitude) > 180) {
        return ['distance' => 0.0, 'configured_radius' => $radius, 'effective_radius' => $effectiveRadius, 'status' => 'Geen locatie'];
      }
    }
    $lat1 = deg2rad($buildingLatitude);
    $lat2 = deg2rad($clockLatitude);
    $deltaLat = deg2rad($clockLatitude - $buildingLatitude);
    $deltaLon = deg2rad($clockLongitude - $buildingLongitude);
    $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    $a = min(1.0, max(0.0, $a));
    $distance = 6371000.0 * 2 * atan2(sqrt($a), sqrt(1.0 - $a));
    return ['distance' => round($distance, 2), 'configured_radius' => $radius, 'effective_radius' => round($effectiveRadius, 2), 'status' => $distance <= $effectiveRadius ? 'Binnen zone' : 'Buiten zone'];
  }

  public function resolveRadius(?float $shiftRadius, ?float $buildingRadius): float {
    if ($shiftRadius !== null && $shiftRadius > 0) return $this->normalizeRadius($shiftRadius);
    if ($buildingRadius !== null && $buildingRadius > 0) return $this->normalizeRadius($buildingRadius);
    return self::DEFAULT_RADIUS_METERS;
  }

  private function normalizeRadius(?float $radius): float {
    if ($radius === null || !is_finite($radius) || $radius <= 0) return self::DEFAULT_RADIUS_METERS;
    return min(5000.0, max(10.0, $radius));
  }
}
