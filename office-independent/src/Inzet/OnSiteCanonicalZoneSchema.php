<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Review-only projection schema; canonical editing stays in brebo_clock_zone.
 * Sync implementation and Drupal-to-Office project identity verification pending.
 */
final class OnSiteCanonicalZoneSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_onsite_canonical_zones (
        zone_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        project_id BIGINT UNSIGNED NOT NULL,
        active TINYINT(1) NOT NULL,
        source_revision BIGINT UNSIGNED NOT NULL,
        synchronized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_onsite_zone_project_active (project_id, active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
