<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;

/**
 * Building-owned component catalogue. Dwelling type and physical location
 * are independent optional placements; quantities remain in inventory rows.
 * Explicit test migration only, never run DDL on class load.
 */
final class OfficeBuildingComponentCatalogue {
  public const SYSTEMS=['NL-SFB','STABU','FREE'];

  public static function codeSystem(string $system): string {
    $normalized=strtoupper(trim($system));
    if (!in_array($normalized,self::SYSTEMS,true)) {
      throw new InvalidArgumentException('unsupported_component_code_system');
    }
    return $normalized;
  }

  public static function statements(): array {
    return [
      "CREATE TABLE office_building_component_definitions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        dwelling_type_id BIGINT UNSIGNED NULL,
        demarcation_id BIGINT UNSIGNED NULL,
        code_system VARCHAR(16) NOT NULL,
        component_code VARCHAR(80) NOT NULL,
        description VARCHAR(255) NOT NULL,
        default_unit VARCHAR(32) NULL,
        source_kind VARCHAR(24) NOT NULL DEFAULT 'manual',
        source_reference VARCHAR(255) NULL,
        approval_status VARCHAR(24) NOT NULL DEFAULT 'concept',
        INDEX idx_component_building_type (building_id,dwelling_type_id),
        INDEX idx_component_building_location (building_id,demarcation_id),
        INDEX idx_component_code (building_id,code_system,component_code),
        FOREIGN KEY (building_id) REFERENCES office_buildings(id),
        FOREIGN KEY (dwelling_type_id) REFERENCES office_building_dwelling_types(id),
        FOREIGN KEY (demarcation_id) REFERENCES office_building_demarcations(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
