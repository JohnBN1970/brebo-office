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
  public static function renderWeek(array $shifts, array $authorizedProjectIds, int $year, int $week, array $projectNames = [], array $manageableProjectIds = [], ?string $cancelCsrfToken = null, bool $rescheduleEnabled = false): string {
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
    $monday = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Brussels')))->setISODate($year,$week,1)->setTime(0,0);
    $people = [];
    $personLabels = [];
    foreach ($rows as $row) {
      $name = trim((string)($row['person_name'] ?? ''));
      $personId = $row['person_id'];
      $number = trim((string)($row['personnel_number'] ?? ''));
      $person = $personId === null ? 'Niet toegewezen' : ($name !== '' ? $name : 'Medewerker')
        . ($number !== '' ? ' · ' . $number : '') . ' (#' . (int)$personId . ')';
      // Stable employee ID is the row key, not the displayed name.
      $personKey = $personId === null ? 'unassigned' : 'person:' . (int)$personId;
      $personLabels[$personKey] = $person;
      $localTz = new \DateTimeZone('Europe/Brussels');
      $start = (new \DateTimeImmutable($row['starts_at'],new \DateTimeZone('UTC')))->setTimezone($localTz);
      $finish = (new \DateTimeImmutable($row['ends_at'],new \DateTimeZone('UTC')))->setTimezone($localTz);
      $weekEnd = $monday->modify('+7 days');
      for ($day = $start->setTime(0,0); $day < $finish && $day < $weekEnd; $day = $day->modify('+1 day')) {
        if ($day < $monday) continue;
        $people[$personKey][$day->format('Y-m-d')][] = $row;
      }
    }
    ksort($people,SORT_NATURAL);
    $html .= '<div style="overflow-x:auto"><table aria-label="Weekrooster per medewerker" style="min-width:840px;width:100%;border-collapse:collapse"><thead><tr><th scope="col">Medewerker</th>';
    foreach ($days as $offset => $label) {
      $date = $monday->modify('+' . $offset . ' days');
      $html .= '<th scope="col">' . $label . '<small style="display:block">' . $date->format('d-m') . '</small></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($people as $personKey => $shiftsByDay) {
      $person = $personLabels[$personKey];
      $html .= '<tr><th scope="row" style="text-align:left;white-space:nowrap">' . $escape($person) . '</th>';
      foreach ($days as $offset => $label) {
        $date = $monday->modify('+' . $offset . ' days')->format('Y-m-d');
        $html .= '<td style="vertical-align:top;min-width:105px">';
        foreach ($shiftsByDay[$date] ?? [] as $shift) {
          $html .= '<div style="padding:8px;margin:3px 0;border:1px solid #cddceb;border-radius:8px;background:#f3f7fc">'
            . '<strong>' . $escape($projectNames[(int)$shift['project_id']] ?? ('Project #' . (int)$shift['project_id'])) . '</strong><small style="display:block">'
            . $escape((new \DateTimeImmutable($shift['starts_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('H:i'))
            . '–' . $escape((new \DateTimeImmutable($shift['ends_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('H:i'))
            . '</small><small>' . $escape($shift['status']) . '</small>';
          if ($cancelCsrfToken !== null && preg_match('/^[a-f0-9]{64}$/D', $cancelCsrfToken)
            && in_array((int)$shift['project_id'], $manageableProjectIds, true)
            && in_array($shift['status'], ['planned','confirmed'], true)
            && (int)($shift['id'] ?? 0) > 0) {
            $html .= '<form method="post" action="/office-test/inzet/dienst/annuleren">'
              . '<input type="hidden" name="csrf_token" value="' . $escape($cancelCsrfToken) . '">'
              . '<input type="hidden" name="shift_id" value="' . (int)$shift['id'] . '">'
              . '<button type="submit">Annuleren</button></form>';
          }
          if ($rescheduleEnabled && $cancelCsrfToken !== null
            && preg_match('/^[a-f0-9]{64}$/D',$cancelCsrfToken)
            && in_array((int)$shift['project_id'],$manageableProjectIds,true)
            && in_array($shift['status'],['planned','confirmed'],true)
            && (int)($shift['id'] ?? 0)>0) {
            $localTz = new \DateTimeZone('Europe/Brussels');
            $startLocal = (new \DateTimeImmutable($shift['starts_at'],new \DateTimeZone('UTC')))->setTimezone($localTz)->format('Y-m-d\\TH:i');
            $endLocal = (new \DateTimeImmutable($shift['ends_at'],new \DateTimeZone('UTC')))->setTimezone($localTz)->format('Y-m-d\\TH:i');
            $html .= '<details><summary>Tijden wijzigen</summary><form method="post" action="/office-test/inzet/dienst/wijzigen">'
              . '<input type="hidden" name="csrf_token" value="' . $escape($cancelCsrfToken) . '">'
              . '<input type="hidden" name="shift_id" value="' . (int)$shift['id'] . '">'
              . '<input type="hidden" name="expected_starts_at" value="' . $escape($shift['starts_at']) . '">'
              . '<input type="hidden" name="expected_ends_at" value="' . $escape($shift['ends_at']) . '">'
              . '<label>Van <input type="datetime-local" name="starts_at_local" required value="' . $escape($startLocal) . '"></label>'
              . '<label>Tot <input type="datetime-local" name="ends_at_local" required value="' . $escape($endLocal) . '"></label>'
              . '<button type="submit">Wijzig dienst</button></form></details>';
          }
          $html .= '</div>';
        }
        $html .= '</td>';
      }
      $html .= '</tr>';
    }
    return $html . '</tbody></table></div><p><small>Alleen-lezen weekrooster. Ontbrekende medewerker- of projectnamen tonen een nummer.</small></p></section>';
  }

  /** @param list<array<string,mixed>> $projects */
  public static function render(array $projects, string $basePath, array $projectTeams = []): string {
    if (!in_array($basePath, ['/office', '/office-test'], true)) {
      throw new \InvalidArgumentException('Invalid Office mount');
    }
    $escape = static fn(mixed $value): string =>
      htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-planning" aria-labelledby="inzet-planning-heading">'
      . '<h2 id="inzet-planning-heading">Projectteams en planning</h2>'
      . '<p>Kies een project om de bestaande project- en gebouwgegevens te bekijken. '
      . 'Het weekrooster verschijnt hieronder zodra de Office-testkoppeling is vrijgegeven.</p>';
    if ($projects === []) {
      return $html . '<p>Geen projecten beschikbaar binnen jouw rechten.</p></section>';
    }
    $html .= '<div class="inzet-project-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0">';
    foreach ($projects as $project) {
      $id = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if ($id === false) continue;
      $url = $basePath . '/projecten/detail?id=' . $id . '#inzet';
      $code = trim((string)($project['code'] ?? ''));
      $title = trim((string)($project['title'] ?? ''));
      $status = trim((string)($project['status'] ?? ''));
      $buildings = filter_var($project['building_count'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
      $html .= '<article style="border:1px solid #dbe1e8;border-radius:12px;padding:18px;background:#f8fafc">'
        . '<small style="display:block;color:#57677b">' . $escape($code) . '</small>'
        . '<h3 style="margin:8px 0"><a href="' . $escape($url) . '">' . $escape($title !== '' ? $title : 'Project #' . $id) . '</a></h3>'
        . '<p style="margin:8px 0">Status: ' . $escape($status !== '' ? $status : 'Onbekend') . '</p>'
        . '<small>' . (is_int($buildings) ? $buildings : 0) . ' gekoppelde gebouwen</small>'
        . '</article>';
      if (array_key_exists($id, $projectTeams) && is_array($projectTeams[$id])) {
        require_once __DIR__ . '/OfficeInzetProjectTeamView.php';
        $editPrefix = getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') === 'enabled'
          && getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') === 'enabled'
          && $basePath === '/office-test'
          ? $basePath . '/projecten?view=inzet&profile_project=' . $id . '&profile_person=' : null;
        $html .= OfficeInzetProjectTeamView::render($projectTeams[$id], $title, $editPrefix);
      }
    }
    return $html . '</div></section>';
  }
}
