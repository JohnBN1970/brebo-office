<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;

/**
 * Address-level building inventory model. Migration is explicit and test-only;
 * no DDL is executed by including this class.
 */
final class OfficeBuildingAddressSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_building_dwelling_types (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        type_code VARCHAR(40) NOT NULL,
        description VARCHAR(255) NOT NULL,
        usable_area_m2 DECIMAL(10,2) NULL,
        layout_json JSON NULL,
        UNIQUE KEY uq_building_type (building_id,type_code),
        FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_building_addresses (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        bag_address_id VARCHAR(128) NULL,
        street VARCHAR(255) NOT NULL,
        house_number INT UNSIGNED NOT NULL,
        house_letter VARCHAR(16) NOT NULL DEFAULT '',
        house_addition VARCHAR(32) NOT NULL DEFAULT '',
        postal_code VARCHAR(16) NULL,
        city VARCHAR(160) NOT NULL,
        dwelling_type_id BIGINT UNSIGNED NULL,
        primary_floor SMALLINT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'concept',
        UNIQUE KEY uq_bag_address (bag_address_id),
        UNIQUE KEY uq_building_address (building_id,street,house_number,house_letter,house_addition,postal_code),
        INDEX idx_address_floor (building_id,primary_floor),
        FOREIGN KEY (building_id) REFERENCES office_buildings(id),
        FOREIGN KEY (dwelling_type_id) REFERENCES office_building_dwelling_types(id),
        CONSTRAINT chk_address_floor CHECK (primary_floor IS NULL OR primary_floor <> 0)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_building_address_floors (
        address_id BIGINT UNSIGNED NOT NULL,
        floor_number SMALLINT NOT NULL,
        PRIMARY KEY (address_id,floor_number),
        FOREIGN KEY (address_id) REFERENCES office_building_addresses(id),
        CONSTRAINT chk_address_floor_number CHECK (floor_number <> 0)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_building_demarcations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(160) NOT NULL,
        description TEXT NULL,
        FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_building_demarcation_addresses (
        demarcation_id BIGINT UNSIGNED NOT NULL,
        address_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (demarcation_id,address_id),
        FOREIGN KEY (demarcation_id) REFERENCES office_building_demarcations(id),
        FOREIGN KEY (address_id) REFERENCES office_building_addresses(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_building_inventory_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        demarcation_id BIGINT UNSIGNED NULL,
        address_id BIGINT UNSIGNED NULL,
        dwelling_type_id BIGINT UNSIGNED NULL,
        component_code VARCHAR(80) NOT NULL,
        description VARCHAR(255) NOT NULL,
        quantity DECIMAL(14,3) NOT NULL,
        unit VARCHAR(32) NOT NULL,
        FOREIGN KEY (building_id) REFERENCES office_buildings(id),
        FOREIGN KEY (demarcation_id) REFERENCES office_building_demarcations(id),
        FOREIGN KEY (address_id) REFERENCES office_building_addresses(id),
        FOREIGN KEY (dwelling_type_id) REFERENCES office_building_dwelling_types(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }

  /** Floor 1 is ground level, negative floors are underground; floor 0 is invalid. */
  public static function floor(?int $number): ?int {
    if ($number === 0) throw new InvalidArgumentException('floor_zero_not_allowed');
    if ($number !== null && ($number < -100 || $number > 300)) {
      throw new InvalidArgumentException('floor_out_of_range');
    }
    return $number;
  }

  /** Both values are optional. A type must belong to the same building (checked on write). */
  public static function validateAddress(?int $floor, ?int $dwellingTypeId): void {
    self::floor($floor);
    if ($dwellingTypeId !== null && $dwellingTypeId < 1) {
      throw new InvalidArgumentException('invalid_dwelling_type');
    }
  }
}
