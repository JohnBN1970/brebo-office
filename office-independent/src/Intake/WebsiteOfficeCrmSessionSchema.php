<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Explicit install step; never run during a website request. */
final class WebsiteOfficeCrmSessionSchema {

  public static function install(PDO $db): void {
    $db->exec(
      'CREATE TABLE IF NOT EXISTS office_operator_sessions (
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        operator_id VARCHAR(128) NOT NULL,
        can_edit_crm TINYINT(1) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        INDEX idx_office_operator_session_expiry (expires_at),
        INDEX idx_office_operator_session_operator (operator_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
  }
}
