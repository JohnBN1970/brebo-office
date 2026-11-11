<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

final class OfficeProjectPortfolioView
{
    /**
     * Projects must be prefiltered by the authenticated operator's access query.
     * Rendered links point to the existing, fully functional project detail route.
     */
    public static function render(array $projects, string $basePath): string
    {
        $escape = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $summary = OfficeProjectPageStructure::portfolioSummary($projects);
        $html = '<section class="office-project-portfolio" aria-label="Projectendashboard">'
            . '<h1>Projectendashboard</h1>'
            . '<p>Overzicht van toegankelijke projecten en hun actuele projectgegevens.</p>'
            . '<div class="office-portfolio-kpis">';
        foreach ([
            'Projecten in overzicht' => $summary['projects'],
            'In voorbereiding' => $summary['preparation'],
            'In uitvoering' => $summary['active'],
            'Gebouwen gekoppeld' => $summary['buildings'],
        ] as $label => $value) {
            $html .= '<article class="office-portfolio-kpi"><small>' . $escape($label)
                . '</small><strong>' . (int) $value . '</strong></article>';
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
        $html .= '</div><section class="office-portfolio-attention" aria-label="Projectaandacht">'
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
        $html .= '</section>';
        $html .= '<p class="office-portfolio-kpi-explanation">Dit zijn aantallen uit de projectenlijst, geen prestatie-KPI’s. Planning, uren, werkbegroting, marge, kwaliteit en risico worden pas als stuurinformatie getoond zodra hun brongegevens per project zijn gecontroleerd.</p><h2>Projectenlijst</h2>'
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
        return '.office-portfolio-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}'
            . '.office-portfolio-kpi{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:20px}'
            . '.office-portfolio-kpi small{display:block;color:#63748a}'
            . '.office-portfolio-kpi strong{display:block;font-size:28px;margin-top:10px}'
            . '.office-portfolio-kpi-explanation{font-size:13px;color:#526278;max-width:850px;margin:-6px 0 24px}.office-portfolio-attention{margin:18px 0;padding:18px;border:1px solid #dbe1e8;border-radius:12px;background:#fff}.office-portfolio-attention h2{margin:0 0 8px;font-size:19px}.office-portfolio-attention p{color:#526278;font-size:13px}.office-portfolio-attention-counts{display:flex;flex-wrap:wrap;gap:12px}.office-portfolio-attention-counts span{padding:8px 12px;background:#f3f6fa;border-radius:8px}.office-portfolio-attention-list{padding-left:20px}.office-portfolio-attention-list li{margin:10px 0}.office-portfolio-attention-list li span{display:block;color:#526278;font-size:13px}.office-portfolio-table-wrap{overflow-x:auto}'
            . '@media(max-width:900px){.office-portfolio-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            . '@media(max-width:500px){.office-portfolio-kpis{grid-template-columns:1fr}}';
    }
}
