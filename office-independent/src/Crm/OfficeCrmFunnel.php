<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/**
 * One commercial pipeline. A lead is qualified into an existing/new prospect
 * relation; customer is a relation role, not a second person record.
 * Recording work (opname) is optional and never a blocking funnel stage.
 */
final class OfficeCrmFunnel {
    public const STAGES = [
        'lead' => 'Lead',
        'prospect' => 'Prospect',
        'opportunity' => 'Verkoopkans',
        'proposal' => 'Offerte',
        'negotiation' => 'Onderhandeling',
        'won' => 'Akkoord',
        'lost' => 'Niet gegund',
    ];
    public const NEXT = [
        'lead' => ['prospect', 'lost'],
        'prospect' => ['opportunity', 'lost'],
        'opportunity' => ['proposal', 'lost'],
        'proposal' => ['negotiation', 'won', 'lost'],
        'negotiation' => ['proposal', 'won', 'lost'],
        'won' => [],
        'lost' => ['prospect', 'lead'],
    ];
    public static function canTransition(string $from, string $to): bool {
        return in_array($to, self::NEXT[$from] ?? [], true);
    }
    public static function normalizeView(string $view): string {
        return in_array($view, ['kanban', 'list', 'calendar'], true) ? $view : 'kanban';
    }
    public static function label(string $stage): string {
        return self::STAGES[$stage] ?? 'Onbekend';
    }
}
