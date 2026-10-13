<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Explicit install step only; never run schema changes during a web request. */
final class OfficeCalculationPermissionSchema {
  public static function install(PDO $db): void {
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_calculation_permissions (
        operator_id VARCHAR(128) NOT NULL PRIMARY KEY,
        can_calculate TINYINT(1) NOT NULL DEFAULT 0,
        CONSTRAINT fk_office_calc_operator FOREIGN KEY (operator_id)
          REFERENCES office_operator_accounts(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
  }
}
