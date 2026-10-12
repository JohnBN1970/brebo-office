<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Explicit schema installation, never executed during a web request. */
final class WebsiteOfficeOperatorAccountSchema {

  public static function install(PDO $db): void {
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_operator_accounts (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        username VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        can_edit_crm TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
  }
}
