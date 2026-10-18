<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use RuntimeException;

/**
 * Pure, fail-closed preflight for an explicitly verified personnel identity map.
 *
 * This class does not decide which system owns personnel, query a database,
 * provision users, or write mappings. Callers must supply verified records
 * from an authoritative source and an existing Office mapping.
 */
final class OfficeInzetIdentityMappingGuard
{
    /**
     * @param list<array{source_system:string,source_person_id:string,office_person_id:int}> $mappings
     * @return array<string,int> keyed by source_system + NUL + source_person_id
     */
    public static function validate(array $mappings): array
    {
        $bySource = [];
        $byOffice = [];
        foreach ($mappings as $mapping) {
            $system = $mapping['source_system'] ?? null;
            $sourceId = $mapping['source_person_id'] ?? null;
            $officeId = $mapping['office_person_id'] ?? null;
            if (!is_string($system) || !preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $system)
                || !is_string($sourceId) || $sourceId === '' || strlen($sourceId) > 128
                || trim($sourceId) !== $sourceId || str_contains($sourceId, "\0")
                || !is_int($officeId) || $officeId < 1) {
                throw new InvalidArgumentException('Unverified personnel identity mapping');
            }

            $key = $system . "\0" . $sourceId;
            if (isset($bySource[$key]) && $bySource[$key] !== $officeId) {
                throw new RuntimeException('Source identity maps to multiple Office people');
            }
            if (isset($byOffice[$officeId]) && $byOffice[$officeId] !== $key) {
                throw new RuntimeException('Office person maps to multiple source identities');
            }
            $bySource[$key] = $officeId;
            $byOffice[$officeId] = $key;
        }
        return $bySource;
    }

    /**
     * A missing mapping is an error, not permission to create a new person.
     *
     * @param array<string,int> $verifiedMap
     */
    public static function requireOfficePerson(array $verifiedMap, string $system, string $sourceId): int
    {
        $key = $system . "\0" . $sourceId;
        if (!isset($verifiedMap[$key])) {
            throw new RuntimeException('Personnel identity has no verified Office mapping');
        }
        return $verifiedMap[$key];
    }
}
