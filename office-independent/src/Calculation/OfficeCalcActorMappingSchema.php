<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Explicit migration only. No automatic schema changes on page load. */
final class OfficeCalcActorMappingSchema {
  public static function install(PDO $db): void {
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_calc_actor_mapping (
        operator_id VARCHAR(128) NOT NULL PRIMARY KEY,
        calc_actor_id BIGINT UNSIGNED NOT NULL UNIQUE,
        CONSTRAINT fk_office_calc_actor_operator FOREIGN KEY (operator_id)
          REFERENCES office_operator_accounts(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
  }
}
