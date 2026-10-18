<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native planning entry point. Only accepts project rows already authorized
 * by the Office session query. No synthetic workers or shifts are displayed.
 */
final class OfficeInzetPlanningWorkbench {
  /**
   * @param list<array<string,mixed>> $shifts Pre-filtered by server-side project access
   * @param list<int> $authorizedProjectIds
   */
  public static function renderWeek(array $shifts, array $authorizedProjectIds, int $year, int $week): string {
    require_once __DIR__ . '/OfficeInzetWeekPlanning.php';
    $rows = OfficeInzetWeekPlanning::rows($shifts, $authorizedProjectIds, $year, $week);
    $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-week"><h2>Weekplanning ' . $year . '-W'
      . str_pad((string)$week, 2, '0', STR_PAD_LEFT) . '</h2>';
    $mondayNav = (new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setISODate($year,$week,1);
    $prev = $mondayNav->modify('-7 days');
    $next = $mondayNav->modify('+7 days');
    $path = '/office-test/projecten?view=inzet';
    $html .= '<nav aria-label="Weeknavigatie" style="display:flex;gap:16px;flex-wrap:wrap;margin:12px 0">'
      . '<a href="' . $path . '&amp;year=' . $prev->format('o') . '&amp;week=' . $prev->format('W') . '">← Vorige week</a>'
      . '<a href="' . $path . '">Huidige week</a>'
      . '<a href="' . $path . '&amp;year=' . $next->format('o') . '&amp;week=' . $next->format('W') . '">Volgende week →</a></nav>';
    if ($rows === []) return $html . '<p>Geen geautoriseerde diensten in deze week.</p></section>';
    $days = ['Ma','Di','Wo','Do','Vr','Za','Zo'];
    $monday = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setISODate($year,$week,1)->setTime(0,0);
    $people = [];
    foreach ($rows as $row) {
      $person = $row['person_id'] === null ? 'Niet toegewezen' : 'Medewerker #' . (int)$row['person_id'];
      $day = (new \DateTimeImmutable($row['starts_at'],new \DateTimeZone('UTC')))->format('Y-m-d');
      $people[$person][$day][] = $row;
    }
    ksort($people,SORT_NATURAL);
    $html .= '<div style="overflow-x:auto"><table aria-label="Weekrooster per medewerker" style="min-width:840px;width:100%;border-collapse:collapse"><thead><tr><th scope="col">Medewerker</th>';
    foreach ($days as $offset => $label) {
      $date = $monday->modify('+' . $offset . ' days');
      $html .= '<th scope="col">' . $label . '<small style="display:block">' . $date->format('d-m') . '</small></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($people as $person => $shiftsByDay) {
      $html .= '<tr><th scope="row" style="text-align:left;white-space:nowrap">' . $escape($person) . '</th>';
      foreach ($days as $offset => $label) {
        $date = $monday->modify('+' . $offset . ' days')->format('Y-m-d');
        $html .= '<td style="vertical-align:top;min-width:105px">';
        foreach ($shiftsByDay[$date] ?? [] as $shift) {
          $html .= '<div style="padding:8px;margin:3px 0;border:1px solid #cddceb;border-radius:8px;background:#f3f7fc">'
            . '<strong>Project #' . (int)$shift['project_id'] . '</strong><small style="display:block">'
            . $escape(substr($shift['starts_at'],11,5)) . '–' . $escape(substr($shift['ends_at'],11,5))
            . '</small><small>' . $escape($shift['status']) . '</small></div>';
        }
        $html .= '</td>';
      }
      $html .= '</tr>';
    }
    return $html . '</tbody></table></div><p><small>Alleen-lezen weekrooster. Medewerker- en projectnamen worden nog niet opgehaald.</small></p></section>';
  }

  /** @param list<array<string,mixed>> $projects */
  public static function render(array $projects, string $basePath): string {
    if (!in_array($basePath, ['/office', '/office-test'], true)) {
      throw new \InvalidArgumentException('Invalid Office mount');
    }
    $escape = static fn(mixed $value): string =>
      htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-planning" aria-labelledby="inzet-planning-heading">'
      . '<h2 id="inzet-planning-heading">Projectteams en planning</h2>'
      . '<p>Selecteer een toegankelijk project. Personeelsplanning, leveranciersbezetting en roosterwijzigingen zijn nog niet aangesloten.</p>';
    if ($projects === []) {
      return $html . '<p>Geen projecten beschikbaar binnen jouw rechten.</p></section>';
    }
    $html .= '<table><thead><tr><th>Project</th><th>Status</th><th>Planning</th></tr></thead><tbody>';
    foreach ($projects as $project) {
      $id = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if ($id === false) continue;
      $url = $basePath . '/projecten/detail?id=' . $id . '#inzet';
      $html .= '<tr><td><a href="' . $escape($url) . '">'
        . $escape($project['code'] ?? '') . ' — ' . $escape($project['title'] ?? '')
        . '</a></td><td>' . $escape($project['status'] ?? '') . '</td>'
        . '<td>Niet aangesloten</td></tr>';
    }
    return $html . '</tbody></table></section>';
  }
}
