<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Server-side session issuance and revocation.
 * Caller MUST authenticate the operator before invoking issue().
 */
final class WebsiteOfficeCrmSessionIssuer {

  public function __construct(private readonly PDO $db) {}

  public function issue(string $operatorId, bool $canEditCrm, int $now, int $ttlSeconds = 28800): string {
    if ($operatorId === '' || strlen($operatorId) > 128 || $ttlSeconds < 60 || $ttlSeconds > 86400) {
      throw new \InvalidArgumentException('Invalid Office session parameters.');
    }
    $token = bin2hex(random_bytes(32));
    $stmt = $this->db->prepare(
      'INSERT INTO office_operator_sessions (token_hash, operator_id, can_edit_crm, expires_at, revoked_at)
       VALUES (:hash, :operator, :can_edit, :expires, NULL)'
    );
    $stmt->execute([
      ':hash' => hash('sha256', $token),
      ':operator' => $operatorId,
      ':can_edit' => (int) $canEditCrm,
      ':expires' => gmdate('Y-m-d H:i:s', $now + $ttlSeconds),
    ]);
    return $token;
  }

  public function revoke(string $token): void {
    if (strlen($token) < 32 || strlen($token) > 256) {
      return;
    }
    $stmt = $this->db->prepare(
      'UPDATE office_operator_sessions SET revoked_at = UTC_TIMESTAMP()
       WHERE token_hash = :hash AND revoked_at IS NULL'
    );
    $stmt->execute([':hash' => hash('sha256', $token)]);
  }

  public function revokeAll(string $operatorId): void {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      throw new \InvalidArgumentException('Invalid Office operator ID.');
    }
    $stmt = $this->db->prepare(
      'UPDATE office_operator_sessions SET revoked_at = UTC_TIMESTAMP()
       WHERE operator_id = :operator AND revoked_at IS NULL'
    );
    $stmt->execute([':operator' => $operatorId]);
  }
}
