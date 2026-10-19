<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Review-only projection schema; canonical editing stays in brebo_clock_zone.
 * Location fields remain nullable until verified coordinates are migrated.
 * Never serve incomplete zone geometry to the mobile geofence client.
 */
final class OnSiteCanonicalZoneSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_onsite_canonical_zones (
        zone_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        project_id BIGINT UNSIGNED NOT NULL,
        active TINYINT(1) NOT NULL,
        zone_name VARCHAR(160) NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        radius_metres DECIMAL(9,2) NULL,
        source_revision BIGINT UNSIGNED NOT NULL,
        synchronized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_onsite_zone_project_active (project_id, active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
