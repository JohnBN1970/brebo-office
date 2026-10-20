<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use RuntimeException;

/**
 * Adapter for the native OfficeInzetShiftCommand (PR #1407).
 * Resolves externally verified source IDs into Office IDs and then delegates
 * the actual write to the native command, which rechecks ACL and team access
 * inside its own write path. Never accepts Office IDs from a source payload.
 */
final class OfficeInzetVerifiedShiftCommand
{
    public function __construct(
        private OfficeInzetVerifiedAssignmentPreflight $preflight,
        private OfficeInzetShiftCommand $nativeCommand
    ) {}

    /** @param array<string,mixed> $request */
    public function createForOperator(
        string $operatorId,
        string $sourceSystem,
        string $sourceProjectId,
        string $sourcePersonId,
        array $request
    ): int {
        if (array_key_exists('project_id', $request) || array_key_exists('person_id', $request)) {
            throw new RuntimeException('Office identity fields cannot be supplied by source');
        }
        $assignment = $this->preflight->verify(
            $operatorId,
            $sourceSystem,
            $sourceProjectId,
            $sourcePersonId
        );
        return $this->nativeCommand->createForOperator($operatorId, array_merge($request, $assignment));
    }
}
