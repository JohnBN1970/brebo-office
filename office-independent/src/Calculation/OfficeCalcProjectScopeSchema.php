<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Opt-in schema installation; no production auto-migration. */
final class OfficeCalcProjectScopeSchema {
  public static function install(PDO $db): void {
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_calc_projects (
        project_id BIGINT UNSIGNED NOT NULL PRIMARY KEY
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_calc_calculations (
        calculation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        project_id BIGINT UNSIGNED NOT NULL,
        CONSTRAINT fk_office_calc_project FOREIGN KEY (project_id)
          REFERENCES office_calc_projects(project_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_calc_project_access (
        operator_id VARCHAR(128) NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (operator_id, project_id),
        CONSTRAINT fk_office_calc_project_operator FOREIGN KEY (operator_id)
          REFERENCES office_operator_accounts(id) ON DELETE CASCADE,
        CONSTRAINT fk_office_calc_access_project FOREIGN KEY (project_id)
          REFERENCES office_calc_projects(project_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
  }
}
