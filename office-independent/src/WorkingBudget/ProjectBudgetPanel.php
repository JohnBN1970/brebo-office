<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Server-rendered, read-only budget panel for an existing authorized Office
 * project page. Caller MUST enforce project ACL and escape output context.
 * No database writes, no external API and no invented financial totals.
 */
final class ProjectBudgetPanel
{
    public static function render(array $view): string
    {
        $projectId = $view['project_id'] ?? null;
        if (!is_int($projectId) || $projectId < 1 || !isset($view['kpis'], $view['source_status'])) {
            throw new InvalidArgumentException('Invalid project budget view.');
        }
        $kpis = $view['kpis'];
        $status = $view['source_status'];
        $fields = [
            'baseline_cents'=>'Vastgestelde werkbegroting',
            'awarded_cents'=>'Verstrekte inkoopopdrachten',
            'actual_cents'=>'Geboekte kosten',
            'open_commitment_cents'=>'Openstaande verplichtingen',
            'forecast_cents'=>'Verwachte eindkosten',
            'variance_cents'=>'Begrotingsafwijking',
        ];
        $html = '<section class="office-work-budget" aria-label="Werkbegroting">';
        $html .= '<h2>Werkbegroting</h2><div class="office-work-budget__kpis">';
        foreach ($fields as $key=>$label) {
            $amount = $kpis[$key] ?? null;
            if ($amount !== null && !is_int($amount)) throw new InvalidArgumentException('Invalid KPI value.');
            $value = $amount === null ? 'Nog niet beschikbaar' : '€ '.number_format($amount / 100, 2, ',', '.');
            $html .= '<div class="office-work-budget__kpi"><strong>'.self::e($label).'</strong><span>'.self::e($value).'</span></div>';
        }
        $html .= '</div><h3>Bronstatus</h3><dl>';
        foreach ($status as $source=>$state) {
            if (!is_scalar($source) || !is_scalar($state)) throw new InvalidArgumentException('Invalid source state.');
            $html .= '<dt>'.self::e((string)$source).'</dt><dd>'.self::e((string)$state).'</dd>';
        }
        $html .= '</dl><p>Prognoses en afwijkingen worden pas getoond wanneer alle benodigde bronnen zijn gecontroleerd.</p></section>';
        return $html;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
