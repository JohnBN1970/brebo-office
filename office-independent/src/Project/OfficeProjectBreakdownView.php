<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/** Escaped read-only project breakdown presentation. */
final class OfficeProjectBreakdownView
{
    public static function render(array $model, int $projectId, string $basePath = '/office-test'): string
    {
        $e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $renderTree = static function (array $nodes) use (&$renderTree, $e): string {
            if ($nodes === []) return '<p>Nog geen onderdelen vastgelegd.</p>';
            $html = '<ul class="office-breakdown-tree">';
            foreach ($nodes as $node) {
                $html .= '<li><strong>' . $e($node['code']) . '</strong> ' . $e($node['title']);
                if (!empty($node['description'])) $html .= '<p>' . $e($node['description']) . '</p>';
                if ($node['children'] !== []) $html .= $renderTree($node['children']);
                $html .= '</li>';
            }
            return $html . '</ul>';
        };
        $matrix = $model['risk_matrix'];
        $html = '<section class="office-breakdown"><h2>Werkstructuur (WBS)</h2>'
            . '<p>Werkpakketten per project; planning en werkbegroting blijven hun eigen bron.</p>'
            . $renderTree($model['wbs']) . '<h2>Risicostructuur (RBS)</h2>'
            . $renderTree($model['rbs']) . '<h3>Risicomatrix (kans × impact)</h3>'
            . '<table><thead><tr><th>Kans / impact</th>';
        for ($impact = 1; $impact <= 5; $impact++) $html .= '<th>' . $impact . '</th>';
        $html .= '</tr></thead><tbody>';
        for ($probability = 5; $probability >= 1; $probability--) {
            $html .= '<tr><th>' . $probability . '</th>';
            for ($impact = 1; $impact <= 5; $impact++) {
                $html .= '<td>' . (int)($matrix['cells'][$probability][$impact] ?? 0) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table><p>Open risico’s: '
            . array_sum($matrix['totals']) . ' · Hoog: ' . (int)$matrix['totals']['high']
            . ' · Kritiek: ' . (int)$matrix['totals']['critical'] . '</p>'
            . '<p><a href="' . $e(rtrim($basePath, '/') . '/risicos?project_id=' . $projectId)
            . '">Risicoregister openen</a></p></section>';
        return $html;
    }
}
