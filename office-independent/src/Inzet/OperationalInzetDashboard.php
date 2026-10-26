<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Server-rendered read-only overview. The caller MUST filter rows by
 * authenticated user/project permissions before calling render().
 */
final class OperationalInzetDashboard {
  /**
   * @param list<array<string,mixed>> $rows Authorized rows only.
   */
  public static function render(array $rows, string $role): string {
    if (!in_array($role, ['werkvloer', 'uitvoerder', 'bedrijfsleiding'], true)) {
      throw new \InvalidArgumentException('Unknown dashboard role');
    }
    $escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $planned = 0;
    $registered = 0;
    $approved = 0;
    $open = 0;
    foreach ($rows as $row) {
      $p = (int)($row['planned_minutes'] ?? 0);
      $r = (int)($row['registered_minutes'] ?? 0);
      $state = (string)($row['state'] ?? '');
      $planned += max(0, $p);
      $registered += max(0, $r);
      if ($state === 'approved') $approved += max(0, $r);
      if ($state === 'submitted' || $state === 'needs_correction') $open++;
    }
    $format = static fn(int $minutes): string => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    $html = '<section class="inzet-operational" aria-labelledby="inzet-operational-title">'
      . '<h2 id="inzet-operational-title">Inzetoverzicht</h2>'
      . '<p>Weergave: '.$escape($role).'. Uren zijn pas vrijgegeven na fiattering.</p>'
      . '<dl><dt>Gepland</dt><dd>'.$format($planned).'</dd>'
      . '<dt>Geregistreerd</dt><dd>'.$format($registered).'</dd>'
      . '<dt>Goedgekeurd</dt><dd>'.$format($approved).'</dd>'
      . '<dt>Open beoordelingen</dt><dd>'.$open.'</dd></dl>'
      . '<table><thead><tr><th>Datum</th><th>Project</th><th>Medewerker</th><th>Gepland</th><th>Geregistreerd</th><th>Afwijking</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
      $p = max(0, (int)($row['planned_minutes'] ?? 0));
      $r = max(0, (int)($row['registered_minutes'] ?? 0));
      $delta = $r - $p;
      $html .= '<tr><td>'.$escape($row['work_date'] ?? '').'</td>'
        .'<td>'.$escape($row['project_id'] ?? '').'</td>'
        .'<td>'.$escape($row['person_id'] ?? '').'</td>'
        .'<td>'.$format($p).'</td><td>'.$format($r).'</td>'
        .'<td>'.($delta < 0 ? '-' : '+').$format(abs($delta)).'</td>'
        .'<td>'.$escape($row['state'] ?? 'gepland').'</td></tr>';
    }
    return $html.'</tbody></table></section>';
  }
}
