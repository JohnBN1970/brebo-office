<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Validates a previously issued Calc grant without handling a raw Office
 * session token. Office's current session and rights remain authoritative.
 *
 * This is a library service, NOT an exposed HTTP endpoint.
 */
final class OfficeCalcGrantReferenceValidator {

  public function __construct(
    private readonly PDO $db,
    private readonly OfficeCalcSessionGrantRepository $grants,
    private readonly PdoOfficeCalculationPermissionRepository $permissions,
    private readonly OfficeCalcProjectScopeRepository $scopes,
    private readonly OfficeCalcActorIdRepository $actors,
  ) {}

  public function isActive(
    string $reference,
    int $actorId,
    int $projectId,
    int $calculationId,
    int $now,
  ): bool {
    $grant = $this->grants->findActive($reference, $actorId, $projectId, $calculationId, $now);
    if ($grant === null) {
      return false;
    }
    $stmt = $this->db->prepare(
      'SELECT s.operator_id FROM office_operator_sessions s
       JOIN office_operator_accounts a ON a.id = s.operator_id
       WHERE s.token_hash = :session_hash AND s.revoked_at IS NULL
       AND s.expires_at > :now AND a.active = 1 LIMIT 1'
    );
    $stmt->execute([
      ':session_hash' => $grant['office_session_hash'],
      ':now' => gmdate('Y-m-d H:i:s', $now),
    ]);
    $operatorId = $stmt->fetchColumn();
    if (!is_string($operatorId) || $operatorId === '' || strlen($operatorId) > 128) {
      return false;
    }
    return $this->actors->findActorId($operatorId) === $actorId
      && $this->permissions->canCalculate($operatorId)
      && $this->scopes->canLaunch($operatorId, $projectId, $calculationId);
  }
}
