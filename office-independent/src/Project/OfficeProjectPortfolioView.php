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
        $html = '<section class="office-project-portfolio" aria-label="Projectenoverzicht">'
            . '<div class="office-portfolio-header"><h2>Alle projecten</h2><p>Zoek een project en open het projectdossier voor planning, uren, financiën en documenten.</p></div>'
            . '<div class="office-portfolio-kpis">';
        foreach ([
            'Projecten' => (int)$summary['projects'],
            'In uitvoering' => (int)$summary['active'],
            'In voorbereiding' => (int)$summary['preparation'],
        ] as $label => $value) {
            $html .= '<article class="office-portfolio-kpi"><small>' . $escape($label)
                . '</small><strong>' . $value . '</strong></article>';
        }
        $html .= '</div>';
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
        $html .= '<div class="office-portfolio-list-header"><h2>Projectenlijst</h2>'
            . '<label class="office-portfolio-search-label" for="office-project-search">Zoek project</label>'
            . '<input id="office-project-search" type="search" placeholder="Zoek op projectcode, naam of status" autocomplete="off" oninput="document.querySelectorAll(\'.office-portfolio-table-wrap tbody tr[data-project-row]\').forEach(function(row){row.hidden=!row.textContent.toLowerCase().includes(this.value.toLowerCase())}.bind(this))"></div>'
            . '<div class="office-portfolio-table-wrap"><table><thead><tr>'
            . '<th>Projectcode</th><th>Project</th><th>Fase</th><th>Planning / aandacht</th><th>Gebouwen</th>'
            . '</tr></thead><tbody>';
        foreach ($projects as $project) {
            $id = (int) ($project['id'] ?? 0);
            if ($id < 1) continue;
            $url = rtrim($basePath, '/') . '/projecten/detail?id=' . $id;
            $html .= '<tr data-project-row><td>' . $escape($project['code'] ?? '') . '</td>'
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
        return '.office-project-portfolio{padding:0 4px 20px}.office-portfolio-header h2{font-size:21px;margin:4px 0 6px}.office-portfolio-header p{color:#526278;margin:0 0 16px}'
            . '.office-portfolio-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0 20px}'
            . '.office-portfolio-kpi{background:#f6f8fb;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px}'
            . '.office-portfolio-kpi small{display:block;color:#63748a}.office-portfolio-kpi strong{display:block;font-size:24px;margin-top:4px}'
            . '.office-portfolio-attention{margin:12px 0 20px;padding:16px;border-left:4px solid #d5a14d;background:#fafbfc;border-radius:8px}'
            . '.office-portfolio-attention h2{font-size:17px;margin:0 0 6px}.office-portfolio-attention p{color:#526278;font-size:13px}'
            . '.office-portfolio-attention-counts{display:flex;flex-wrap:wrap;gap:8px}.office-portfolio-attention-counts span{font-size:12px;padding:6px 9px;background:#fff;border:1px solid #e2e8f0;border-radius:6px}'
            . '.office-portfolio-attention-list{padding-left:18px}.office-portfolio-attention-list li{margin:8px 0}.office-portfolio-attention-list li span{display:block;color:#526278;font-size:12px}'
            . '.office-portfolio-list-header{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:20px 0 12px}.office-portfolio-list-header h2{margin:0 auto 0 0;font-size:20px}'
            . '.office-portfolio-search-label{font-size:13px;color:#526278}.office-portfolio-list-header input{border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;min-width:250px}'
            . '.office-portfolio-table-wrap{overflow-x:auto}.office-portfolio-table-wrap table{width:100%;border-collapse:collapse}.office-portfolio-table-wrap th,.office-portfolio-table-wrap td{text-align:left;padding:13px 12px;border-bottom:1px solid #e2e8f0}.office-portfolio-table-wrap thead{background:#f5f7fa}'
            . '.office-portfolio-table-wrap tbody tr:hover{background:#f6f9fc}.office-portfolio-table-wrap tr[hidden]{display:none}'
            . '@media(max-width:700px){.office-portfolio-kpis{grid-template-columns:1fr 1fr}.office-portfolio-list-header input{width:100%}}';
    }
}
