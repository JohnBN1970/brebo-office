<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native, read-only source review. Input is restricted to server-authorized
 * supplier imports; never treats workbook labels as verified identities.
 */
final class OfficeInzetSourceReviewWorkbench {
  /** @param array<string,mixed> $report */
  public static function render(array $report): string {
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
    $html .= '<details open><summary>Bronafwijkingen ('.(int)($report['warning_count'] ?? count($issues)).')</summary>';
    if (($report['warnings_complete'] ?? true) !== true) {
      $html .= '<p>Alleen de eerste controlepunten worden getoond; de volledige lijst is groter.</p>';
    }
    if ($issues === []) $html .= '<p>Geen afwijkingen gemeld door deze preflight.</p>';
    else {
      $html .= '<table><thead><tr><th>Blad</th><th>Cel</th><th>Reden</th></tr></thead><tbody>';
      foreach (array_slice($issues, 0, 200) as $issue) {
        if (!is_array($issue)) continue;
        $html .= '<tr><td>'.$escape($issue['sheet'] ?? '').'</td><td>'
          .$escape($issue['cell'] ?? '').'</td><td>'.$escape($issue['reason'] ?? '').'</td></tr>';
      }
      $html .= '</tbody></table>';
    }
    return $html.'</details><p>Alle beslissingen vereisen een bevoegde Office-gebruiker. Factuurvrijgave is niet beschikbaar.</p></section>';
  }
}
