<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
/**
 * Review-only fixture of the Office building register observed on Office-test.
 * Never migrate existing buildings to a parallel name/address/active schema.
 */
final class OfficeBuildingSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_buildings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(128) NOT NULL,
        name VARCHAR(255) NOT NULL,
        address_line VARCHAR(255) NULL,
        postal_code VARCHAR(32) NULL,
        city VARCHAR(128) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_project_buildings (
        project_id BIGINT UNSIGNED NOT NULL,
        building_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (project_id, building_id),
        INDEX idx_office_project_buildings_building (building_id),
        CONSTRAINT fk_office_project_building_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}

