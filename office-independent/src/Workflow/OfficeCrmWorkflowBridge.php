<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

/**
 * Read-only compatibility map for the existing CRM funnel.
 * Does not treat CRM 'won' as proof of a signed/accepted contract.
 * Until CRM stores explicit acceptance evidence, this adapter MUST NOT
 * execute an order, create a project, or trigger FinanceCenter.
 */
final class OfficeCrmWorkflowBridge
{
    private const STAGE_TO_STATE = [
        'lead' => 'intake',
        'prospect' => 'qualified',
        'opportunity' => 'opportunity',
        'proposal' => 'quotation_draft',
        'negotiation' => 'quotation_review',
        'won' => 'accepted',
        'lost' => 'lost',
    ];

    public static function mappedState(string $crmStage): string
    {
        if (!isset(self::STAGE_TO_STATE[$crmStage])) {
            throw new \DomainException('Unknown CRM funnel stage.');
        }
        return self::STAGE_TO_STATE[$crmStage];
    }

    public static function requiresAcceptanceEvidence(string $targetCrmStage): bool
    {
        return $targetCrmStage === 'won';
    }

    public static function assertNoAutomaticAcceptance(string $targetCrmStage, bool $verifiedHumanAcceptance): void
    {
        if (self::requiresAcceptanceEvidence($targetCrmStage) && !$verifiedHumanAcceptance) {
            throw new \DomainException('Verified human acceptance evidence required before CRM won.');
        }
    }
}
