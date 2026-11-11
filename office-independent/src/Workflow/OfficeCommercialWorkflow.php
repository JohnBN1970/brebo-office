<?php
declare(strict_types=1);

namespace Brebo\Office\Workflow;

/**
 * Version 1 immutable commercial workflow definition.
 * No Drupal dependencies, no automatic financial side effects.
 * Future installation-admin editor must implement the same transition contract.
 */
final class OfficeCommercialWorkflow
{
    public const VERSION = 'commercial-v1';

    private const TRANSITIONS = [
        'intake' => ['qualify' => 'qualified'],
        'qualified' => ['open_opportunity' => 'opportunity', 'register_direct_order' => 'accepted'],
        'opportunity' => ['start_quotation' => 'quotation_draft', 'register_direct_order' => 'accepted', 'close_lost' => 'lost'],
        'quotation_draft' => ['submit_review' => 'quotation_review', 'close_lost' => 'lost'],
        'quotation_review' => ['request_changes' => 'quotation_draft', 'approve' => 'quotation_approved'],
        'quotation_approved' => ['send' => 'quotation_sent'],
        'quotation_sent' => ['accept' => 'accepted', 'reject' => 'lost', 'revise' => 'quotation_draft'],
        'accepted' => ['start_fulfilment' => 'fulfilment'],
        'fulfilment' => ['complete' => 'completed'],
        'lost' => [],
        'completed' => [],
    ];

    public static function definition(): array
    {
        return ['id' => 'commercial', 'version' => self::VERSION, 'transitions' => self::TRANSITIONS];
    }

    public static function next(string $state, string $event): string
    {
        if (!isset(self::TRANSITIONS[$state][$event])) {
            throw new \DomainException('Commercial workflow transition not permitted.');
        }
        return self::TRANSITIONS[$state][$event];
    }

    public static function requiresHumanApproval(string $event): bool
    {
        return in_array($event, ['register_direct_order', 'approve', 'accept', 'start_fulfilment'], true);
    }

    public static function allowsAutomaticExecution(string $event): bool
    {
        return !self::requiresHumanApproval($event) && !in_array($event, ['send', 'complete'], true);
    }
}
