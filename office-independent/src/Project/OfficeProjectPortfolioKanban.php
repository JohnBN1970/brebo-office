<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/** Read-only native project Kanban: only authorized project rows from caller. */
final class OfficeProjectPortfolioKanban {
  public static function render(array $projects, string $base): string {
    $e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $groups = [];
    foreach ($projects as $project) {
      $phase = trim((string)($project['phase'] ?? ''));
      // Do not guess a lifecycle phase from the free-form project status.
      $group = $phase !== '' ? $phase : 'Fase nog niet vastgesteld';
      $groups[$group][] = $project;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    $html = '<style>.office-kanban{display:flex;gap:16px;overflow-x:auto;padding:10px 0 18px}.office-kanban-col{flex:0 0 290px;background:#f4f6fa;border:1px solid #dbe1e8;border-radius:12px;padding:14px}.office-kanban-card{background:white;border:1px solid #dbe1e8;border-radius:9px;padding:14px;margin-top:12px}.office-kanban-card a{font-weight:650}.office-kanban-meta{font-size:12px;color:#526278;margin-top:9px}.office-kanban-note{font-size:12px;color:#64748b}</style>';
    $html .= '<div class="office-kanban-note">Projectfase wordt alleen getoond wanneer deze in de bron is vastgelegd. Kaarten zijn alleen-lezen; faseovergangen volgen na autorisatie- en workflowcontrole.</div>';
    if ($projects === []) return $html.'<p>Geen toegankelijke projecten.</p>';
    $html .= '<div class="office-kanban">';
    foreach ($groups as $phase => $items) {
      $html .= '<section class="office-kanban-col"><h2 style="font-size:16px;margin:0">'.$e($phase).' ('.count($items).')</h2>';
      foreach ($items as $project) {
        $id = (int)($project['id'] ?? 0);
        if ($id <= 0) continue;
        $url = $base.'/projecten/detail?id='.$id;
        $html .= '<article class="office-kanban-card"><a href="'.$e($url).'">'.$e($project['title'] ?? 'Project').'</a>';
        $html .= '<div class="office-kanban-meta">'.$e($project['code'] ?? '').' · Status: '.$e($project['status'] ?? 'Onbekend').'</div>';
        $html .= '<div style="margin-top:10px">'.OfficeProjectTrafficLight::render($project).'</div>';
        if (isset($project['project_leader']) && trim((string)$project['project_leader']) !== '') $html .= '<div class="office-kanban-meta">Projectleider: '.$e($project['project_leader']).'</div>';
        if (isset($project['planned_end']) && trim((string)$project['planned_end']) !== '') $html .= '<div class="office-kanban-meta">Geplande einddatum: '.$e($project['planned_end']).'</div>';
        $html .= '</article>';
      }
      $html .= '</section>';
    }
    return $html.'</div>';
  }
}
