<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Read-only weekly visualization. Eight hours is a reference, not a verified shift. */
final class OfficeInzetWeekCalendar {
  public static function render(array $sessions, \DateTimeImmutable $monday, callable $escape): string {
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $date = $monday->modify('+' . $i . ' days');
      $days[$date->format('Y-m-d')] = $date;
    }
    $byPerson = [];
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
      $out .= '<tr><th style="text-align:left">#' . $person . '</th>';
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
    }
    if ($byPerson === []) $out .= '<tr><td colspan="9">Geen afgeronde OnSite-sessies in deze week.</td></tr>';
    return $out . '</tbody></table></div><p style="font-size:12px">Grijs = referentie van 8 uur op werkdagen, kleur = geregistreerde OnSite-uren. Dit is geen geverifieerde planning of urenfiattering. Overuren blijven als percentage boven 100% zichtbaar.</p>';
  }
}
