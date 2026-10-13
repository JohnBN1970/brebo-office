<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Explicit schema installation only; no live auto-migration. */
final class OfficeCalcProjectContextSchema {
  public static function install(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS office_calc_project_context (
      project_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      project_code VARCHAR(128) NOT NULL,
      title VARCHAR(255) NOT NULL,
      status VARCHAR(32) NOT NULL,
      client_name VARCHAR(255) NOT NULL,
      client_organization_id BIGINT UNSIGNED NULL,
      client_organization_title VARCHAR(255) NULL,
      project_kind VARCHAR(64) NOT NULL,
      disciplines_json JSON NOT NULL,
      description TEXT NOT NULL,
      CONSTRAINT fk_office_calc_context_project FOREIGN KEY (project_id)
        REFERENCES office_calc_projects(project_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS office_calc_project_context_buildings (
      project_id BIGINT UNSIGNED NOT NULL,
      building_id BIGINT UNSIGNED NOT NULL,
      title VARCHAR(255) NOT NULL,
      PRIMARY KEY (project_id, building_id),
      CONSTRAINT fk_office_calc_context_building_project FOREIGN KEY (project_id)
        REFERENCES office_calc_project_context(project_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  }
}
