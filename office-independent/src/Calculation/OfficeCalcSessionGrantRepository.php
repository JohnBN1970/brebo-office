<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Opaque Calc grant references are hashed at rest and bound to an existing
 * Office session, actor and project/calculation scope.
 *
 * Schema (reviewed migration required before use):
 * office_calc_session_grants (
 *   reference_hash CHAR(64) PRIMARY KEY,
 *   office_session_hash CHAR(64) NOT NULL,
 *   actor_id BIGINT NOT NULL,
 *   project_id BIGINT NOT NULL,
 *   calculation_id BIGINT NOT NULL,
 *   expires_at DATETIME NOT NULL,
 *   revoked_at DATETIME NULL
 * )
 */
final class OfficeCalcSessionGrantRepository {

  public function __construct(private readonly PDO $db) {}

  public function issue(
    string $officeSessionToken,
    int $actorId,
    int $projectId,
    int $calculationId,
    int $expiresAt,
  ): string {
    if (strlen($officeSessionToken) < 32 || strlen($officeSessionToken) > 256
      || $actorId < 1 || $projectId < 1 || $calculationId < 1
      || $expiresAt <= time() || $expiresAt > time() + 28800) {
      throw new \InvalidArgumentException('Invalid Calc session grant.');
    }
    return $this->issueForSessionHash(
      hash('sha256', $officeSessionToken), $actorId, $projectId, $calculationId, $expiresAt
    );
  }

  /** Verified Office operator contexts expose a session hash, not a raw cookie. */
  public function issueForSessionHash(
    string $officeSessionHash,
    int $actorId,
    int $projectId,
    int $calculationId,
    int $expiresAt,
  ): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $officeSessionHash)
      || $actorId < 1 || $projectId < 1 || $calculationId < 1
      || $expiresAt <= time() || $expiresAt > time() + 28800) {
      throw new \InvalidArgumentException('Invalid Calc session grant.');
    }
    $reference = bin2hex(random_bytes(32));
    $stmt = $this->db->prepare(
      'INSERT INTO office_calc_session_grants
       (reference_hash, office_session_hash, actor_id, project_id, calculation_id, expires_at)
       VALUES (:reference_hash, :office_session_hash, :actor_id, :project_id, :calculation_id, :expires_at)'
    );
    $stmt->execute([
      ':reference_hash' => hash('sha256', $reference),
      ':office_session_hash' => $officeSessionHash,
      ':actor_id' => $actorId,
      ':project_id' => $projectId,
      ':calculation_id' => $calculationId,
      ':expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
    ]);
    return $reference;
  }

  public function findActive(
    string $reference,
    int $actorId,
    int $projectId,
    int $calculationId,
    int $now,
  ): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $reference)
      || $actorId < 1 || $projectId < 1 || $calculationId < 1 || $now < 1) {
      return null;
    }
    $stmt = $this->db->prepare(
      'SELECT office_session_hash FROM office_calc_session_grants
       WHERE reference_hash = :reference_hash AND actor_id = :actor_id
       AND project_id = :project_id AND calculation_id = :calculation_id
       AND revoked_at IS NULL AND expires_at > :now LIMIT 1'
    );
    $stmt->execute([
      ':reference_hash' => hash('sha256', $reference),
      ':actor_id' => $actorId,
      ':project_id' => $projectId,
      ':calculation_id' => $calculationId,
      ':now' => gmdate('Y-m-d H:i:s', $now),
    ]);
    $hash = $stmt->fetchColumn();
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash)
      ? ['office_session_hash' => $hash] : null;
  }

  public function revokeForOfficeSession(string $officeSessionToken): void {
    if (strlen($officeSessionToken) < 32 || strlen($officeSessionToken) > 256) {
      return;
    }
    $stmt = $this->db->prepare(
      'UPDATE office_calc_session_grants SET revoked_at = UTC_TIMESTAMP()
       WHERE office_session_hash = :office_session_hash AND revoked_at IS NULL'
    );
    $stmt->execute([':office_session_hash' => hash('sha256', $officeSessionToken)]);
  }
}
