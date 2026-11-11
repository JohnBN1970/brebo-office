<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

final class OfficeProjectPortfolioView
{
    /**
     * Projects must be prefiltered by the authenticated operator's access query.
     * Rendered links point to the existing, fully functional project detail route.
     */
    public static function render(array $projects, string $basePath, array $totals = []): string
    {
        $escape = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $summary = OfficeProjectPageStructure::portfolioSummary($projects);
        $html = '<section class="office-project-portfolio" aria-label="Projectendashboard">'
            . '<h1>Projectendashboard</h1>'
            . '<p>Overzicht van toegankelijke projecten en hun actuele projectgegevens.</p>'
            . '<div class="office-portfolio-kpis">';
        // Financial and labour totals are deliberately unavailable until sourced
        // from authorized, reconciled Office aggregates (never inferred from counts).
        foreach ([
            'Totale opdrachtwaarde' => isset($totals['contract_value_cents']) && is_int($totals['contract_value_cents']) ? '€ ' . number_format($totals['contract_value_cents'] / 100, 2, ',', '.') : 'Niet beschikbaar',
            'Vastgestelde inkoopbegroting' => isset($totals['procurement_budget_cents']) && is_int($totals['procurement_budget_cents']) ? '€ ' . number_format($totals['procurement_budget_cents'] / 100, 2, ',', '.') : 'Niet beschikbaar',
            'Werkelijke kosten' => 'Niet beschikbaar',
            'Geplande uren (geselecteerde week)' => isset($totals['planned_week_seconds']) && is_int($totals['planned_week_seconds']) && $totals['planned_week_seconds'] >= 0 ? number_format($totals['planned_week_seconds'] / 3600, 2, ',', '.') . ' uur' : 'Niet beschikbaar',
            'Geregistreerde uren' => 'Niet beschikbaar',
            'Gefactureerd' => 'Niet beschikbaar',
        ] as $label => $value) {
            $html .= '<article class="office-portfolio-kpi"><small>' . $escape($label)
                . '</small><strong>' . $escape($value) . '</strong></article>';
        }
        $coverage = isset($totals['procurement_covered'], $totals['procurement_total'])
            ? ' Vastgestelde inkoopbegrotingen: ' . max(0, (int)$totals['procurement_covered'])
                . ' van ' . max(0, (int)$totals['procurement_total']) . ' toegankelijke projecten.'
            : ' Dekking van de inkoopbegrotingen is niet beschikbaar.';
        $contractCoverage = isset($totals['contract_covered'], $totals['contract_total'])
            ? ' Geaccepteerde contracten: ' . max(0, (int)$totals['contract_covered']) . ' van ' . max(0, (int)$totals['contract_total']) . ' projecten.'
            : ' Dekking van contractwaarden is niet beschikbaar.';
        $html .= '</div><p class="office-portfolio-total-scope">Geplande uren gelden voor de geselecteerde ISO-week (' . $escape($totals['planned_week_label'] ?? 'onbekend') . '), uitsluitend geplande en bevestigde diensten; geannuleerde diensten uitgesloten; geen geboekte uren. Financiële totalen worden uitsluitend getoond bij volledige bron- en rechtencontrole. Ontbrekende gegevens tellen niet als nul. Inkoopbegroting is exclusief arbeid en btw.'
            . $escape($coverage . $contractCoverage) . '</p>';
        if (isset($totals['planned_week_label']) && preg_match('/^(\\d{4})-W(\\d{2})$/D', (string)$totals['planned_week_label'], $weekMatch)) {
            $weekDate = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Brussels')))
                ->setISODate((int)$weekMatch[1], (int)$weekMatch[2], 1);
            $weekLink = static fn(\DateTimeImmutable $date): string =>
                rtrim($basePath, '/') . '/projecten?week=' . $date->format('o-\\WW');
            $html .= '<nav aria-label="Planning weekselectie" class="office-portfolio-week-navigation">'
                . '<a href="' . $escape($weekLink($weekDate->modify('-7 days'))) . '">Vorige week</a> '
                . '<strong>' . $escape($totals['planned_week_label']) . '</strong> '
                . '<a href="' . $escape($weekLink($weekDate->modify('+7 days'))) . '">Volgende week</a></nav>';
        }
        $assessed = ['red' => 0, 'orange' => 0, 'green' => 0, 'gray' => 0];
        $closed = 0;
        $withPlanning = 0;
        $attentionProjects = [];
        foreach ($projects as $project) {
            $id = (int)($project['id'] ?? 0);
            if ($id < 1) continue;
            if (!empty($project['plan_items']) && is_array($project['plan_items'])) ++$withPlanning;
            $light = OfficeProjectTrafficLight::assess($project);
            $color = (string)($light['color'] ?? 'gray');
            if (!array_key_exists($color, $assessed)) $color = 'gray';
            if ($color === 'gray' && ($light['label'] ?? '') === 'Afgesloten') {
                ++$closed;
            } else {
                ++$assessed[$color];
            }
            if (in_array($color, ['red', 'orange'], true)) {
                $attentionProjects[] = ['project' => $project, 'light' => $light];
            }
        }
        $html .= '<section class="office-portfolio-attention" aria-label="Projectaandacht">'
            . '<h2>Projecten die aandacht vragen</h2>'
            . '<p>Signalen uit vastgelegde projectstatus en beschikbare planningsregels. Geen beoordeling van financiën of kwaliteit.</p>'
            . '<div class="office-portfolio-attention-counts">'
            . '<span><strong>' . $assessed['red'] . '</strong> Kritiek</span>'
            . '<span><strong>' . $assessed['orange'] . '</strong> Aandacht</span>'
            . '<span><strong>' . $assessed['green'] . '</strong> Op schema (planning)</span>'
            . '<span><strong>' . $assessed['gray'] . '</strong> Onbekend</span>'
            . '<span><strong>' . $closed . '</strong> Afgesloten</span>'
            . '<span><strong>' . $withPlanning . '</strong> Met planningsregels</span></div>';
        if ($attentionProjects === []) {
            $html .= '<p>Geen geregistreerde kritieke of wachtende planningssignalen in deze selectie. Dit is geen garantie dat alle projecten op schema liggen.</p>';
        } else {
            $html .= '<ul class="office-portfolio-attention-list">';
            foreach ($attentionProjects as $item) {
                $project = $item['project'];
                $id = (int)$project['id'];
                $url = rtrim($basePath, '/') . '/projecten/detail?id=' . $id;
                $html .= '<li><a href="' . $escape($url) . '">' . $escape($project['title'] ?? 'Project') . '</a>'
                    . ' <strong>' . $escape($item['light']['label']) . '</strong>'
                    . '<span>' . $escape($item['light']['reason']) . '</span></li>';
            }
            $html .= '</ul>';
        }
        $accessibleCount = $totals['accessible_project_count'] ?? null;
        if (is_int($accessibleCount) && $accessibleCount > count($projects)) {
            $html .= '<p class="office-portfolio-total-scope">Projectenlijst en planningssignalen tonen '
                . count($projects) . ' van ' . $accessibleCount
                . ' toegankelijke projecten. Financiële totalen gebruiken alle toegankelijke projecten.</p>';
        }
        $html .= '</section>';
        $html .= '<p class="office-portfolio-kpi-explanation">Projectaantallen: ' . (int) $summary['projects'] . ' projecten, ' . (int) $summary['active'] . ' in uitvoering, ' . (int) $summary['preparation'] . ' in voorbereiding en ' . (int) $summary['buildings'] . ' gebouwkoppelingen. Overige bedrag- en urentotalen volgen na broncontrole.</p><h2>Projectenlijst</h2>'
            . '<div class="office-portfolio-table-wrap"><table><thead><tr>'
            . '<th>Projectcode</th><th>Project</th><th>Status</th><th>Planning / aandacht</th><th>Gebouwen</th>'
            . '</tr></thead><tbody>';
        foreach ($projects as $project) {
            $id = (int) ($project['id'] ?? 0);
            if ($id < 1) continue;
            $url = rtrim($basePath, '/') . '/projecten/detail?id=' . $id;
            $html .= '<tr><td>' . $escape($project['code'] ?? '') . '</td>'
                . '<td><a href="' . $escape($url) . '">' . $escape($project['title'] ?? '') . '</a></td>'
                . '<td>' . $escape(OfficeProjectPageStructure::statusLabel((string) ($project['status'] ?? ''))) . '</td>'
                . '<td>' . OfficeProjectTrafficLight::render($project) . '</td>'
                . '<td>' . max(0, (int) ($project['building_count'] ?? 0)) . '</td></tr>';
        }
        if (!$projects) $html .= '<tr><td colspan="5">Geen toegankelijke projecten gevonden.</td></tr>';
        return $html . '</tbody></table></div></section>';
    }

    public static function styles(): string
    {
        return '.office-portfolio-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:20px 0}'
            . '.office-portfolio-kpi{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:20px}'
            . '.office-portfolio-total-scope{grid-column:1/-1;color:#526278;font-size:13px;margin:0}.office-portfolio-kpi small{display:block;color:#63748a}'
            . '.office-portfolio-kpi strong{display:block;font-size:28px;margin-top:10px}'
            . '.office-portfolio-kpi-explanation{font-size:13px;color:#526278;max-width:850px;margin:-6px 0 24px}.office-portfolio-attention{margin:18px 0;padding:18px;border:1px solid #dbe1e8;border-radius:12px;background:#fff}.office-portfolio-attention h2{margin:0 0 8px;font-size:19px}.office-portfolio-attention p{color:#526278;font-size:13px}.office-portfolio-attention-counts{display:flex;flex-wrap:wrap;gap:12px}.office-portfolio-attention-counts span{padding:8px 12px;background:#f3f6fa;border-radius:8px}.office-portfolio-attention-list{padding-left:20px}.office-portfolio-attention-list li{margin:10px 0}.office-portfolio-attention-list li span{display:block;color:#526278;font-size:13px}.office-portfolio-table-wrap{overflow-x:auto}'
            . '@media(max-width:900px){.office-portfolio-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            . '@media(max-width:500px){.office-portfolio-kpis{grid-template-columns:1fr}}';
    }
}
