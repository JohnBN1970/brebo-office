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
        if (!preg_match('/^[a-f0-9]{64}$/D', $reference)
            || $actorId <= 0 || $projectId <= 0 || $calculationId <= 0 || $now <= 0) {
            return false;
        }
        try {
            require_once __DIR__ . '/OfficeCalcSessionGrantRepository.php';
            if (!method_exists(OfficeCalcSessionGrantRepository::class, 'findActive')) {
                return false;
            }
            $result = (new OfficeCalcSessionGrantRepository($this->db))->findActive(
                $reference, $actorId, $projectId, $calculationId, $now
            );
            // A false/null result is always denied. Other shapes are deliberately
            // denied until the installed repository's return contract is verified.
            return $result === true;
        } catch (Throwable) {
            return false;
        }
    }
}
