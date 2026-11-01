<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Read-only weekly visualization. Eight hours is a reference, not a verified shift. */
final class OfficeInzetWeekCalendar {
  /** KPI values use the same weekly overlap calculation as the calendar. */
  public static function kpis(array $sessions, \DateTimeImmutable $monday): array {
    $start = $monday->setTime(0, 0)->getTimestamp();
    $end = $monday->modify('+7 days')->setTime(0, 0)->getTimestamp();
    $total = 0;
    $people = [];
    $projects = [];
    foreach ($sessions as $session) {
      try {
        $from = (new \DateTimeImmutable((string)$session['started_at']))->getTimestamp();
        $until = (new \DateTimeImmutable((string)$session['ended_at']))->getTimestamp();
      } catch (\Throwable $error) { continue; }
      $seconds = max(0, min($until, $end) - max($from, $start));
      if ($seconds === 0) continue;
      $person = (int)($session['person_id'] ?? 0);
      $project = (int)($session['project_id'] ?? 0);
      if ($person <= 0 || $project <= 0) continue;
      $total += $seconds;
      $people[$person] = true;
      $projects[$project] = true;
    }
    return ['seconds'=>$total,'people'=>count($people),'projects'=>count($projects)];
  }

  public static function renderKpis(array $sessions, \DateTimeImmutable $monday): string {
    $data = self::kpis($sessions, $monday);
    $hours = $data['seconds'] / 3600;
    $people = $data['people'];
    $reference = $people * 40;
    $values = [
      ['Geregistreerde uren', number_format($hours, 2, ',', '.') . ' u', 'OnSite-sessies in geselecteerde week'],
      ['Actieve medewerkers', (string)$people, 'Met afgeronde sessies'],
      ['Actieve projecten', (string)$data['projects'], 'Met geregistreerde uren'],
      ['Gemiddeld per medewerker', $people ? number_format($hours / $people, 2, ',', '.') . ' u' : '—', 'Over actieve medewerkers'],
      ['40-uursreferentie', $reference ? number_format($hours / $reference * 100, 1, ',', '.') . '%' : '—', 'Geen geverifieerde planning'],
    ];
    $out = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin:16px 0">';
    foreach ($values as [$label,$value,$note]) {
      $out .= '<div style="border:1px solid #dbe4ed;background:linear-gradient(150deg,#ffffff,#f0f5fa);border-radius:12px;padding:14px;box-shadow:0 3px 10px #142b4410">';
      $out .= '<div style="font-size:12px;color:#526477;font-weight:650">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
      $out .= '<div style="font-size:25px;font-weight:800;color:#185b8f;margin:8px 0">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>';
      $out .= '<div style="font-size:11px;color:#64748b">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</div></div>';
    }
    return $out . '</div>';
  }
  public static function render(array $sessions, \DateTimeImmutable $monday, callable $escape, array $projectNames = []): string {
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $date = $monday->modify('+' . $i . ' days');
      $days[$date->format('Y-m-d')] = $date;
    }
    $byPerson = [];
    $byProject = [];
    foreach ($sessions as $session) {
      $person = (int)($session['person_id'] ?? 0);
      if ($person <= 0) continue;
      try {
        $start = new \DateTimeImmutable((string)$session['started_at']);
        $end = new \DateTimeImmutable((string)$session['ended_at']);
      } catch (\Throwable $e) {
        continue;
      }
      if ($end <= $start) continue;
      // Split sessions across midnight and week boundaries, using local date.
      foreach ($days as $dateKey => $date) {
        $dayStart = $date->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $overlap = max(0, min($end->getTimestamp(), $dayEnd->getTimestamp()) - max($start->getTimestamp(), $dayStart->getTimestamp()));
        if ($overlap > 0) {
          $byPerson[$person][$dateKey] = ($byPerson[$person][$dateKey] ?? 0) + $overlap;
          $projectId = (int)($session['project_id'] ?? 0);
          $byProject[$person][$projectId][$dateKey] = ($byProject[$person][$projectId][$dateKey] ?? 0) + $overlap;
        }
      }
    }
    ksort($byPerson);
    $out = '<div style="overflow-x:auto"><table style="width:100%;min-width:850px;border-collapse:separate;border-spacing:5px"><thead><tr><th style="text-align:left">Medewerker</th>';
    foreach ($days as $date) {
      $out .= '<th>' . $escape(['ma','di','wo','do','vr','za','zo'][(int)$date->format('N')-1] . ' ' . $date->format('d-m')) . '</th>';
    }
    $out .= '<th>Totaal</th></tr></thead><tbody>';
    foreach ($byPerson as $person => $values) {
      $total = array_sum($values);
      $out .= '<tr><th style="text-align:left">Medewerker #' . $person . '</th>';
      foreach ($days as $key => $date) {
        $seconds = $values[$key] ?? 0;
        $reference = (int)$date->format('N') <= 5 ? 8 * 3600 : 0;
        $percent = $reference > 0 ? round($seconds / $reference * 100, 1) : null;
        $fill = $reference > 0 ? min(100, $percent) : ($seconds > 0 ? 100 : 0);
        $color = $reference === 0 ? '#426ca8' : ($percent >= 100 ? '#23845b' : ($percent >= 85 ? '#d59b25' : '#cc574c'));
        $out .= '<td style="min-width:92px;background:#f4f6fa;padding:8px;border-radius:8px">';
        $out .= '<div style="height:12px;background:#dbe1e8;border-radius:9px;overflow:hidden"><div style="width:' . $fill . '%;height:100%;background:' . $color . '"></div></div>';
        $out .= '<div style="font-size:12px;text-align:center;margin-top:5px">' . number_format($seconds / 3600, 2, ',', '.') . ' u';
        $out .= $percent === null ? ' / vrij' : ' / 8 u (' . number_format($percent, 1, ',', '.') . '%)';
        $out .= '</div></td>';
      }
      $out .= '<td style="white-space:nowrap">' . number_format($total / 3600, 2, ',', '.') . ' u</td></tr>';
      if (isset($byProject[$person])) {
        $projectRows = '';
        foreach ($byProject[$person] as $projectId => $projectDays) {
          $projectRows .= '<tr><th style="text-align:left;padding-left:18px">' . $escape((string)($projectNames[$projectId] ?? ('Project #' . $projectId))) . '</th>';
          foreach ($days as $dayKey => $date) {
            $seconds = $projectDays[$dayKey] ?? 0;
            $share = ($values[$dayKey] ?? 0) > 0 ? min(100, $seconds / $values[$dayKey] * 100) : 0;
            $projectRows .= '<td style="background:#f4f6fa;padding:8px"><div style="height:12px;background:#dbe1e8;border-radius:9px;overflow:hidden"><div style="height:100%;width:' . round($share, 1) . '%;background:#426ca8"></div></div><div style="text-align:center;font-size:12px">' . number_format($seconds / 3600, 2, ',', '.') . ' u</div></td>';
          }
          $projectRows .= '<td>' . number_format(array_sum($projectDays) / 3600, 2, ',', '.') . ' u</td></tr>';
        }
        $out .= '<tr><td colspan="9"><details><summary style="cursor:pointer">▸ Projecten van medewerker #' . $person . '</summary><table style="width:100%;table-layout:fixed"><tbody>' . $projectRows . '</tbody></table></details></td></tr>';
      }
    }
    if ($byPerson === []) $out .= '<tr><td colspan="9">Geen afgeronde OnSite-sessies in deze week.</td></tr>';
    return $out . '</tbody></table></div><p style="font-size:12px">Grijs = referentie van 8 uur op werkdagen, kleur = geregistreerde OnSite-uren. Dit is geen geverifieerde planning of urenfiattering. Overuren blijven als percentage boven 100% zichtbaar.</p>';
  }
}
