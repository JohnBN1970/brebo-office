<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Read-only, fail-closed Office session verification by SHA-256 token hash.
 * Does not accept a browser-provided actor or an unscoped grant as proof.
 */
final class OfficeCalcActiveOfficeSession
{
    public function __construct(private readonly PDO $db) {}

    public function isActive(string $sessionHash, int $actorId, int $now): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $sessionHash) || $actorId <= 0 || $now <= 0) {
            return false;
        }
        try {
            $statement = $this->db->prepare(
                'SELECT 1 FROM office_operator_sessions s
                 INNER JOIN office_operator_accounts a ON a.id = s.operator_id
                 WHERE s.token_hash = :hash AND s.operator_id = :actor
                   AND s.revoked_at IS NULL AND s.expires_at > :now
                   AND a.active = 1 LIMIT 1'
            );
            $statement->execute([
                ':hash' => $sessionHash,
                ':actor' => (string) $actorId,
                ':now' => gmdate('Y-m-d H:i:s', $now),
            ]);
            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
