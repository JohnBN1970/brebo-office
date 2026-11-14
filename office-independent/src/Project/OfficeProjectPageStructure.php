<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Shared project page semantics. GOTIK is an internal management model,
 * never a second user-facing navigation hierarchy.
 */
final class OfficeProjectPageStructure
{
    public const PORTFOLIO_TITLE = 'Projectoverzicht';
    public const DETAIL_TITLE = 'Projectdetailpagina';

    public static function detailTabs(): array
    {
        return [
            'overzicht' => 'Overzicht',
            'gebouwen' => 'Gebouwen',
            'werkvoorbereiding' => 'Werkvoorbereiding',
            'uitvoering' => 'Uitvoering',
            'arbeid-inzet' => 'Arbeid & Inzet',
            'financieel' => 'Werkbegroting',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'preparation', 'voorbereiding' => 'Voorbereiding',
            'active', 'in_progress', 'execution', 'uitvoering' => 'In uitvoering',
            'completed', 'finished', 'afgerond' => 'Afgerond',
            'cancelled', 'canceled', 'geannuleerd' => 'Geannuleerd',
            'draft', 'concept' => 'Concept',
            '' => 'Niet vastgelegd',
            default => (string) $status,
        };
    }

    /**
     * Aggregates only accessible projects already selected by the caller.
     * Never calculates contract or personnel totals from unverified sources.
     */
    public static function portfolioSummary(array $projects): array
    {
        $buildings = 0;
        $preparation = 0;
        $active = 0;
        foreach ($projects as $project) {
            $buildings += max(0, (int) ($project['building_count'] ?? 0));
            $status = strtolower(trim((string) ($project['status'] ?? '')));
            if ($status === 'preparation') ++$preparation;
            if (in_array($status, ['active', 'in_progress', 'execution'], true)) ++$active;
        }
        return [
            'projects' => count($projects),
            'buildings' => $buildings,
            'preparation' => $preparation,
            'active' => $active,
        ];
    }
}
