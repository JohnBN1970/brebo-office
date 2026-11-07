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
            if (!class_exists(OfficeCalcSessionGrantRepository::class, false)) {
                require_once __DIR__ . '/OfficeCalcSessionGrantRepository.php';
            }
            if (!method_exists(OfficeCalcSessionGrantRepository::class, 'findActive')) {
                return false;
            }
            $result = (new OfficeCalcSessionGrantRepository($this->db))->findActive(
                $reference, $actorId, $projectId, $calculationId, $now
            );
            // Installed Office-test returns ?array, not boolean. Accept only a
            // non-empty record whose identity matches the requested grant.
            // Current project/calculation ACL is rechecked by the caller.
            if (!is_array($result) || $result === []) {
                return false;
            }
            $expected = [
                'reference' => $reference,
                'actor_id' => $actorId,
                'project_id' => $projectId,
                'calculation_id' => $calculationId,
            ];
            foreach ($expected as $field => $value) {
                if (!array_key_exists($field, $result)) {
                    return false;
                }
                if ($field === 'reference') {
                    if (!is_string($result[$field]) || !hash_equals($value, $result[$field])) {
                        return false;
                    }
                } elseif (!is_int($result[$field]) && !(is_string($result[$field]) && ctype_digit($result[$field]))) {
                    return false;
                } elseif ((string) $result[$field] !== (string) $value) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
