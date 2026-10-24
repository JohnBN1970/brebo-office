<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Target Office-native zone ownership model.
 * Building owns its zones; projects access them through office_project_buildings.
 * Review-only schema; no migration or production write is performed here.
 */
final class OfficeBuildingZoneSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_building_zones (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        zone_name VARCHAR(160) NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        radius_metres DECIMAL(9,2) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        request_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        UNIQUE KEY uq_office_building_zone_request (request_key),
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_office_building_zone_building FOREIGN KEY (building_id)
          REFERENCES office_buildings(id),
        INDEX idx_office_building_zone_building_active (building_id,active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
