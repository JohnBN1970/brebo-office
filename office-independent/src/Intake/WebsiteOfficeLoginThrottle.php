<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Shared MySQL login throttling. All callers must check before authenticating. */
final class WebsiteOfficeLoginThrottle {

  public function __construct(private readonly PDO $db) {}

  public static function install(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS office_login_attempts (
      identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
      attempts INT NOT NULL DEFAULT 0,
      window_start DATETIME NOT NULL,
      blocked_until DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  }

  public function allowed(string $identity, int $now): bool {
    $stmt = $this->db->prepare('SELECT blocked_until FROM office_login_attempts WHERE identity_hash = ?');
    $stmt->execute([hash('sha256', $identity)]);
    $until = $stmt->fetchColumn();
    return $until === false || $until === null || strtotime((string) $until . ' UTC') <= $now;
  }

  public function failed(string $identity, int $now): void {
    $hash = hash('sha256', $identity);
    $date = gmdate('Y-m-d H:i:s', $now);
    $stmt = $this->db->prepare('INSERT INTO office_login_attempts (identity_hash, attempts, window_start, blocked_until)
      VALUES (?, 1, ?, NULL)
      ON DUPLICATE KEY UPDATE
      blocked_until = IF(window_start < DATE_SUB(VALUES(window_start), INTERVAL 15 MINUTE), NULL, IF(attempts >= 4, DATE_ADD(VALUES(window_start), INTERVAL 15 MINUTE), blocked_until)),
      attempts = IF(window_start < DATE_SUB(VALUES(window_start), INTERVAL 15 MINUTE), 1, attempts + 1),
      window_start = IF(window_start < DATE_SUB(VALUES(window_start), INTERVAL 15 MINUTE), VALUES(window_start), window_start)');
    $stmt->execute([$hash, $date]);
  }

  public function reset(string $identity): void {
    $stmt = $this->db->prepare('DELETE FROM office_login_attempts WHERE identity_hash = ?');
    $stmt->execute([hash('sha256', $identity)]);
  }
}
