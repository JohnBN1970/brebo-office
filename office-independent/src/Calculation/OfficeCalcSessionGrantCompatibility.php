<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Adapter for the installed Office-test grant repository API.
 *
 * The existing repository is authoritative: do not replace it with a
 * parallel class of the same name. Fail closed on missing/incompatible API.
 * This check does NOT replace current Office project/calculation ACL checks.
 */
final class OfficeCalcSessionGrantCompatibility
{
    public function __construct(private readonly PDO $db) {}

    public function findActive(
        string $reference,
        int $actorId,
        int $projectId,
        int $calculationId,
        int $now
    ): bool {
        return $this->activeSessionHash($reference, $actorId, $projectId, $calculationId, $now) !== null;
    }

    public function activeSessionHash(
        string $reference,
        int $actorId,
        int $projectId,
        int $calculationId,
        int $now
    ): ?string {
        if (!preg_match('/^[a-f0-9]{64}$/D', $reference)
            || $actorId <= 0 || $projectId <= 0 || $calculationId <= 0 || $now <= 0) {
            return null;
        }
        try {
            if (!class_exists(OfficeCalcSessionGrantRepository::class, false)) {
                require_once __DIR__ . '/OfficeCalcSessionGrantRepository.php';
            }
            if (!method_exists(OfficeCalcSessionGrantRepository::class, 'findActive')) {
                return null;
            }
            $result = (new OfficeCalcSessionGrantRepository($this->db))->findActive(
                $reference, $actorId, $projectId, $calculationId, $now
            );
            // The installed repository performs its own scoped grant query,
            // returning only the associated Office session hash on success.
            // Do not accept other record shapes or a malformed session hash.
            // The caller must additionally enforce current Office ACL.
            if (!is_array($result) || count($result) !== 1
                || !isset($result['office_session_hash'])
                || !is_string($result['office_session_hash'])) {
                return null;
            }
            return preg_match('/^[a-f0-9]{64}$/D', $result['office_session_hash']) === 1
                ? $result['office_session_hash'] : null;
        } catch (Throwable) {
            return null;
        }
    }
}
