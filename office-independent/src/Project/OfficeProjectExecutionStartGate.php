<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use DomainException;

/**
 * Mandatory server-side precondition for starting a project execution scope.
 * Integration point: call immediately before committing a start/activation
 * transaction, using project/scope resolved from trusted Office records.
 * Do not derive these identifiers from untrusted POST fields.
 */
final class OfficeProjectExecutionStartGate
{
    public function __construct(private readonly OfficeProjectKickstartRepository $dossiers) {}

    public function authorizeStart(
        int $projectId,
        string $scopeType,
        int $scopeId,
        bool $projectAccessGranted,
        bool $canStartExecution
    ): void {
        if (!$projectAccessGranted || !$canStartExecution) {
            throw new DomainException('Execution blocked: insufficient project permissions');
        }
        $this->dossiers->assertReleased($projectId, $scopeType, $scopeId);
    }
}
