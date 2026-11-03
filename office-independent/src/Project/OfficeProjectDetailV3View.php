<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Pure presentation of one authorized Office project.
 * The caller remains responsible for authorization, repositories and POST actions.
 */
final class OfficeProjectDetailV3View
{
    public static function render(
        string $basePath,
        int $projectId,
        string $title,
        string $code,
        ?string $status,
        int $buildingCount,
        string $tab,
        array $sections = []
    ): string {
        $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tabs = OfficeProjectPageStructure::detailTabs();
        if (!isset($tabs[$tab])) $tab = 'overzicht';
        $base = rtrim($basePath, '/') . '/projecten/detail?id=' . $projectId;
        $html = '<div class="office-project-detail-v3">'
            . '<nav aria-label="Kruimelpad" class="project-v3-breadcrumb"><a href="' . $e(rtrim($basePath, '/') . '/projecten') . '">Projectendashboard</a> / ' . $e($code) . '</nav>'
            . '<header class="project-v3-header"><div><p class="project-v3-eyebrow">Projectdetailpagina</p>'
            . '<h1>' . $e($title) . '</h1><p>' . $e($code) . ' · ' . $e(OfficeProjectPageStructure::statusLabel($status)) . '</p>'
            . '</div></header><nav class="project-v3-tabs" aria-label="Projectonderdelen">';
        foreach ($tabs as $key => $label) {
            $html .= '<a href="' . $e($base . '&tab=' . rawurlencode($key)) . '"'
                . ($tab === $key ? ' aria-current="page"' : '') . '>' . $e($label) . '</a>';
        }
        $html .= '</nav><main class="project-v3-content">';
        if ($tab === 'overzicht' && !isset($sections['overzicht'])) {
            $html .= '<section aria-label="Projectdashboard"><h2>Projectdashboard</h2>'
                . '<div class="project-v3-kpis">'
                . '<article><small>Gebouwen</small><strong>' . max(0, $buildingCount) . '</strong><p>Gekoppeld aan dit project</p></article>'
                . '<article><small>Projectstatus</small><strong>' . $e(OfficeProjectPageStructure::statusLabel($status)) . '</strong><p>BREBO Office</p></article>'
                . '<article><small>Contractwaarde</small><strong>—</strong><p>Financiële bron nog niet gekoppeld</p></article>'
                . '<article><small>Voortgang</small><strong>—</strong><p>Uitvoeringsbron nog niet gekoppeld</p></article>'
                . '</div></section>';
        }
        if (isset($sections[$tab]) && is_string($sections[$tab])) {
            // Trusted HTML only, supplied by existing authorized Office renderers.
            $html .= $sections[$tab];
        } elseif ($tab !== 'overzicht') {
            $html .= '<section class="project-v3-placeholder"><h2>' . $e($tabs[$tab]) . '</h2>'
                . '<p>De bestaande Office-functionaliteit wordt hier aangesloten.</p></section>';
        }
        return $html . '</main></div>';
    }

    public static function styles(): string
    {
        return '.office-project-detail-v3{min-width:0;max-width:100%}'
            . '.project-v3-breadcrumb{margin-bottom:16px;color:#617389}'
            . '.project-v3-header{background:#fff;border:1px solid #dbe1e8;border-radius:14px;padding:24px;margin-bottom:16px}'
            . '.project-v3-header h1{margin:6px 0;font-size:clamp(24px,2.3vw,36px)}'
            . '.project-v3-eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#60738a}'
            . '.project-v3-tabs{display:flex;gap:8px;flex-wrap:wrap;background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:10px;margin-bottom:18px}'
            . '.project-v3-tabs a{padding:10px 13px;border-radius:8px;text-decoration:none;color:#30516d;font-weight:600}'
            . '.project-v3-tabs a[aria-current]{background:#30516d;color:#fff}'
            . '.project-v3-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}'
            . '.project-v3-kpis article,.project-v3-placeholder{border:1px solid #dbe1e8;border-radius:12px;background:#fff;padding:20px;min-width:0}'
            . '.project-v3-kpis small{display:block;color:#64748b}'
            . '.project-v3-kpis strong{display:block;font-size:clamp(20px,2vw,30px);margin:8px 0}'
            . '@media(max-width:950px){.project-v3-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            . '@media(max-width:550px){.project-v3-kpis{grid-template-columns:1fr}}';
    }
}
