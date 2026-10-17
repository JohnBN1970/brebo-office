<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native, read-only source review. Input is restricted to server-authorized
 * supplier imports; never treats workbook labels as verified identities.
 */
final class OfficeInzetSourceReviewWorkbench {
  /** @param array<string,mixed> $report */
  public static function render(array $report, array $filters = []): string {
    $escape = static fn(mixed $v): string =>
      htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-review" aria-labelledby="inzet-review-heading">'
      . '<h2 id="inzet-review-heading">Controle urenverantwoording</h2>'
      . '<p>Brongegevens ter beoordeling. Namen en projecten zijn nog niet bevestigd; uren zijn niet goedgekeurd.</p>';
    $stats = [
      'Bronregels' => 'row_count',
      'Naamvermeldingen' => 'person_label_count',
      'Projectvermeldingen' => 'project_label_count',
      'Controlepunten' => 'warning_count',
    ];
    $html .= '<div class="inzet-kpis">';
    foreach ($stats as $label=>$key) {
      $html .= '<article class="inzet-kpi"><span>'.$escape($label).'</span><strong>'
        .(int)($report[$key] ?? 0).'</strong></article>';
    }
    $html .= '</div>';
    foreach (['person_labels'=>'Te koppelen medewerkers','project_labels'=>'Te koppelen projecten'] as $key=>$label) {
      $items = $report[$key] ?? [];
      if (!is_array($items)) $items = [];
      $html .= '<details open><summary>'.$escape($label).' ('.count($items).')</summary><ul>';
      foreach (array_slice($items, 0, 150) as $value) {
        $html .= '<li>'.$escape($value).'</li>';
      }
      $html .= '</ul></details>';
    }
    $issues = $report['warnings'] ?? [];
    if (!is_array($issues)) $issues = [];
    $filterFields = ['person'=>'Medewerker', 'project'=>'Project', 'sheet'=>'Week / werkblad'];
    $html .= '<form method="get" action=""><fieldset><legend>Afwijkingen filteren</legend>';
    foreach ($filterFields as $field=>$label) {
      $html .= '<label for="filter-'.$field.'">'.$escape($label).'</label> '
        . '<input id="filter-'.$field.'" name="'.$field.'" value="'.$escape($filters[$field] ?? '').'" maxlength="120"> ';
    }
    $html .= '<button type="submit">Filter</button> <a href="?">Wissen</a></fieldset></form>';
    $html .= '<details id="afwijkingen" open><summary>Bronafwijkingen ('.(int)($report['warning_count'] ?? count($issues)).')</summary>';
    if (($report['warnings_complete'] ?? true) !== true) {
      $html .= '<p>Alleen de eerste controlepunten worden getoond; de volledige lijst is groter.</p>';
    }
    // Summarize only the currently loaded issues. Never present a partial
    // group count as the total when the source list was truncated.
    $issues = array_values(array_filter($issues, static function ($issue) use ($filters): bool {
      if (!is_array($issue)) return false;
      foreach (['person', 'project', 'sheet'] as $field) {
        $needle = trim((string)($filters[$field] ?? ''));
        if ($needle !== '' && stripos((string)($issue[$field] ?? ''), $needle) === false) return false;
      }
      return true;
    }));
    $html .= '<p>'.count($issues).' afwijkingen binnen de gekozen filters (van '.(int)($report['warning_count'] ?? 0).' totaal).</p>';
    $groups = [];
    foreach ($issues as $issue) {
      if (!is_array($issue)) continue;
      $sheet = (string)($issue['sheet'] ?? 'Onbekend blad');
      $reason = (string)($issue['reason'] ?? 'Onbekend');
      $key = $sheet . "\\0" . $reason;
      if (!isset($groups[$key])) $groups[$key] = ['sheet'=>$sheet, 'reason'=>$reason, 'count'=>0];
      $groups[$key]['count']++;
    }
    uasort($groups, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
    if ($groups !== []) {
      $html .= '<h3>Afwijkingen per werkblad en reden</h3>';
      $html .= '<p>Groepering van de getoonde controlepunten, niet noodzakelijk alle bronafwijkingen.</p>';
      $html .= '<table><thead><tr><th>Werkblad</th><th>Reden</th><th>Aantal getoond</th></tr></thead><tbody>';
      foreach ($groups as $group) {
        $html .= '<tr><td>'.$escape($group['sheet']).'</td><td>'.$escape($group['reason'])
          .'</td><td>'.(int)$group['count'].'</td></tr>';
      }
      $html .= '</tbody></table>';
    }
    if ($issues === []) $html .= '<p>Geen afwijkingen gemeld door deze preflight.</p>';
    else {
      $html .= '<table><thead><tr><th>ID</th><th>Week / blad</th><th>Bronregel</th><th>Medewerker (bron)</th><th>Project (bron)</th><th>Cel</th><th>Reden</th></tr></thead><tbody>';
      foreach (array_slice($issues, 0, 200) as $issue) {
        if (!is_array($issue)) continue;
        $html .= '<tr><td>'.$escape($issue['issue_id'] ?? '').'</td><td>'
          .$escape($issue['sheet'] ?? '').'</td><td>'.$escape($issue['source_row'] ?? '').'</td><td>'
          .$escape($issue['person'] ?? '').'</td><td>'.$escape($issue['project'] ?? '').'</td><td>'
          .$escape($issue['cell'] ?? '').'</td><td>'.$escape($issue['reason'] ?? '').'</td></tr>';
      }
      $html .= '</tbody></table>';
    }
    return $html.'</details><p>Alle beslissingen vereisen een bevoegde Office-gebruiker. Factuurvrijgave is niet beschikbaar.</p></section>';
  }
}
