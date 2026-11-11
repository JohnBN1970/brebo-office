<?php
declare(strict_types=1);

namespace Brebo\Office\Workflow;

/**
 * Application-facing guard for the hardcoded commercial workflow.
 * This class only validates an intended transition; persistence and side effects
 * must be performed by the calling module in its own authorized transaction.
 */
final class OfficeCommercialTransitionGuard
{
    public static function authorize(
        string $currentState,
        string $event,
        bool $authenticated,
        bool $authorizedForCase,
        bool $humanConfirmed,
        string $actorType = 'human'
    ): string {
        if (!$authenticated || !$authorizedForCase) {
            throw new \DomainException('Commercial workflow access denied.');
        }
        if ($actorType !== 'human' && $actorType !== 'system') {
            throw new \DomainException('Unknown workflow actor.');
        }
        if ($actorType === 'system' && !OfficeCommercialWorkflow::allowsAutomaticExecution($event)) {
            throw new \DomainException('This transition cannot be executed automatically.');
        }
        if (OfficeCommercialWorkflow::requiresHumanApproval($event)
            && ($actorType !== 'human' || !$humanConfirmed)) {
            throw new \DomainException('Human confirmation is required.');
        }
        return OfficeCommercialWorkflow::next($currentState, $event);
    }
}
