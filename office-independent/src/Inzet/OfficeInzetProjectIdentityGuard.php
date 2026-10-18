<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use RuntimeException;

/** Read-only identity preflight. Never provisions projects, people or access. */
final class OfficeInzetProjectIdentityGuard
{
    /**
     * @param list<array{source_system:string,source_project_id:string,office_project_id:int}> $rows
     * @return array<string,int>
     */
    public static function validate(array $rows): array
    {
        $forward = [];
        $reverse = [];
        foreach ($rows as $row) {
            $system = $row['source_system'] ?? null;
            $source = $row['source_project_id'] ?? null;
            $office = $row['office_project_id'] ?? null;
            if (!is_string($system) || !preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $system)
                || !is_string($source) || $source === '' || strlen($source) > 128
                || trim($source) !== $source || str_contains($source, "\0")
                || !is_int($office) || $office < 1) {
                throw new InvalidArgumentException('Invalid verified project identity');
            }
            $key = $system . "\0" . $source;
            if (isset($forward[$key]) && $forward[$key] !== $office) {
                throw new RuntimeException('Conflicting source project mapping');
            }
            if (isset($reverse[$office]) && $reverse[$office] !== $key) {
                throw new RuntimeException('Conflicting Office project mapping');
            }
            $forward[$key] = $office;
            $reverse[$office] = $key;
        }
        return $forward;
    }

    /** @param array<string,int> $verified */
    public static function requireProject(array $verified, string $system, string $source): int
    {
        $key = $system . "\0" . $source;
        if (!isset($verified[$key])) {
            throw new RuntimeException('Project has no verified Office identity');
        }
        return $verified[$key];
    }
}
