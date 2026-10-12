<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Read-only validation of an Office-managed session. Never trusts user IDs or
 * permissions sent by the browser. Session secrets are stored as SHA-256 hashes.
 *
 * Expected Office-owned table: office_operator_sessions
 * (token_hash CHAR(64) PRIMARY KEY, operator_id VARCHAR(128),
 *  can_edit_crm TINYINT, expires_at DATETIME, revoked_at DATETIME NULL).
 */
final class WebsiteOfficeCrmSessionRepository {

  public function __construct(private readonly PDO $db) {}

  public function findActive(string $sessionToken, int $now): ?WebsiteCrmOperatorContext {
    if (strlen($sessionToken) < 32 || strlen($sessionToken) > 256) {
      return null;
    }
    $stmt = $this->db->prepare(
      'SELECT operator_id, can_edit_crm FROM office_operator_sessions
       WHERE token_hash = :hash AND revoked_at IS NULL
       AND expires_at > :now LIMIT 1'
    );
    $stmt->execute([
      ':hash' => hash('sha256', $sessionToken),
      ':now' => gmdate('Y-m-d H:i:s', $now),
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false || !is_string($row['operator_id']) || $row['operator_id'] === '') {
      return null;
    }
    return new WebsiteCrmOperatorContext(
      $row['operator_id'],
      hash('sha256', $sessionToken),
      (int) $row['can_edit_crm'] === 1,
    );
  }
}
