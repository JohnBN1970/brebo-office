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
        $html .= '</div><p class="office-portfolio-kpi-explanation">Dit zijn aantallen uit de projectenlijst, geen prestatie-KPI’s. Planning, uren, werkbegroting, marge, kwaliteit en risico worden pas als stuurinformatie getoond zodra hun brongegevens per project zijn gecontroleerd.</p><h2>Projectenlijst</h2>'
            . '<div class="office-portfolio-table-wrap"><table><thead><tr>'
            . '<th>Projectcode</th><th>Project</th><th>Status</th><th>Gebouwen</th>'
            . '</tr></thead><tbody>';
        foreach ($projects as $project) {
            $id = (int) ($project['id'] ?? 0);
            if ($id < 1) continue;
            $url = rtrim($basePath, '/') . '/projecten/detail?id=' . $id;
            $html .= '<tr><td>' . $escape($project['code'] ?? '') . '</td>'
                . '<td><a href="' . $escape($url) . '">' . $escape($project['title'] ?? '') . '</a></td>'
                . '<td>' . $escape(OfficeProjectPageStructure::statusLabel((string) ($project['status'] ?? ''))) . '</td>'
                . '<td>' . max(0, (int) ($project['building_count'] ?? 0)) . '</td></tr>';
        }
        if (!$projects) $html .= '<tr><td colspan="4">Geen toegankelijke projecten gevonden.</td></tr>';
        return $html . '</tbody></table></div></section>';
    }

    public static function styles(): string
    {
        return '.office-portfolio-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0}'
            . '.office-portfolio-kpi{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:20px}'
            . '.office-portfolio-kpi small{display:block;color:#63748a}'
            . '.office-portfolio-kpi strong{display:block;font-size:28px;margin-top:10px}'
            . '.office-portfolio-kpi-explanation{font-size:13px;color:#526278;max-width:850px;margin:-6px 0 24px}.office-portfolio-table-wrap{overflow-x:auto}'
            . '@media(max-width:900px){.office-portfolio-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            . '@media(max-width:500px){.office-portfolio-kpis{grid-template-columns:1fr}}';
    }
}
