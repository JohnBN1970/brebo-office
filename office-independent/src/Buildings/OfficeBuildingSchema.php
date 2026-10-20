<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Office-native building identity, independent from Drupal.
 * Review-only DDL. Apply to isolated Office test DB through an explicit migration.
 * A building may be linked to several projects and vice versa.
 */
final class OfficeBuildingSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_buildings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_name VARCHAR(160) NOT NULL,
        street_address VARCHAR(255) NULL,
        postal_code VARCHAR(32) NULL,
        city VARCHAR(160) NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_project_buildings (
        project_id BIGINT UNSIGNED NOT NULL,
        building_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (project_id, building_id),
        INDEX idx_office_project_buildings_building (building_id),
        CONSTRAINT fk_office_project_building_building
          FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
