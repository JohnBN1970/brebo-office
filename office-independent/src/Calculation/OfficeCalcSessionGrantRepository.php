<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Persistent, revocable Office Calc grants.
 * Authorization MUST be performed by the caller before issuing a grant.
 * A grant is bound to the hashed Office session, actor, project and calculation.
 */
final class OfficeCalcSessionGrantRepository
{
    public function __construct(private readonly PDO $db) {}

    public function issue(
        string $sessionHash,
        int $actorId,
        int $projectId,
        int $calculationId,
        int $now,
        int $ttlSeconds = 28800
    ): string {
        self::assertIdentity($sessionHash, $actorId, $projectId, $calculationId);
        if ($now <= 0 || $ttlSeconds < 1 || $ttlSeconds > 28800) {
            throw new \InvalidArgumentException('Invalid grant expiry');
        }
        $reference = bin2hex(random_bytes(32));
        $statement = $this->db->prepare(
            'INSERT INTO office_calc_session_grants
              (reference, session_hash, actor_id, project_id, calculation_id, expires_at)
             VALUES (:reference, :session_hash, :actor_id, :project_id, :calculation_id, :expires_at)'
        );
        $statement->execute([
            ':reference' => $reference,
            ':session_hash' => $sessionHash,
            ':actor_id' => $actorId,
            ':project_id' => $projectId,
            ':calculation_id' => $calculationId,
            ':expires_at' => gmdate('Y-m-d H:i:s', $now + $ttlSeconds),
        ]);
        return $reference;
    }

    /**
     * The caller must ALSO re-evaluate the current Office project/calculation
     * permission before accepting a grant. This check alone is insufficient.
     */
    public function matchesActiveSession(
        string $reference,
        int $actorId,
        int $projectId,
        int $calculationId,
        int $now
    ): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D', $reference)
            || $actorId <= 0 || $projectId <= 0 || $calculationId <= 0 || $now <= 0) {
            return false;
        }
        $statement = $this->db->prepare(
            'SELECT 1 FROM office_calc_session_grants g
             INNER JOIN office_operator_sessions s ON s.token_hash = g.session_hash
             INNER JOIN office_operator_accounts a ON a.id = g.actor_id
             WHERE g.reference = :reference AND g.actor_id = :actor_id
               AND g.project_id = :project_id AND g.calculation_id = :calculation_id
               AND g.revoked_at IS NULL AND g.expires_at > :now
               AND s.revoked_at IS NULL AND s.expires_at > :now
               AND a.active = 1 LIMIT 1'
        );
        $statement->execute([
            ':reference' => $reference,
            ':actor_id' => $actorId,
            ':project_id' => $projectId,
            ':calculation_id' => $calculationId,
            ':now' => gmdate('Y-m-d H:i:s', $now),
        ]);
        return $statement->fetchColumn() !== false;
    }

    public function revoke(string $reference): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $reference)) {
            throw new \InvalidArgumentException('Invalid grant reference');
        }
        $statement = $this->db->prepare(
            'UPDATE office_calc_session_grants SET revoked_at = UTC_TIMESTAMP()
             WHERE reference = :reference AND revoked_at IS NULL'
        );
        $statement->execute([':reference' => $reference]);
    }

    private static function assertIdentity(
        string $sessionHash, int $actorId, int $projectId, int $calculationId
    ): void {
        if (!preg_match('/^[a-f0-9]{64}$/D', $sessionHash)
            || $actorId <= 0 || $projectId <= 0 || $calculationId <= 0) {
            throw new \InvalidArgumentException('Invalid Office grant identity');
        }
    }
}
