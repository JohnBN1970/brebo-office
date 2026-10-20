<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native Office Inzet landing panel. Only renders server-authorized aggregates.
 * Never guesses staffing, attendance or invoice KPIs from project counts.
 */
final class OfficeInzetDashboard {
  /** @param list<array<string,mixed>> $projects */
  public static function render(array $projects, ?int $visibleSupplierWeeks = null): string {
    $projectCount = count($projects);
    $buildingCount = 0;
    foreach ($projects as $project) {
      $buildingCount += max(0, (int)($project['building_count'] ?? 0));
    }
    $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $cards = [
      ['Toegankelijke projecten', (string)$projectCount, 'Office-projectrechten'],
      ['Project-gebouwkoppelingen', (string)$buildingCount, 'Geen unieke gebouwtelling'],
      ['Leveranciersweekstaten', $visibleSupplierWeeks === null ? 'Niet aangesloten' : (string)$visibleSupplierWeeks, 'Alleen volledig toegankelijke bronbestanden'],
      ['Aanwezigheid en afwijkingen', 'Niet aangesloten', 'OnSite-integratie volgt na API- en rechtencontrole'],
    ];
    $html = '<div class="inzet-dashboard" aria-label="Inzet werkplek"><div class="inzet-kpis">';
    foreach ($cards as [$label, $value, $note]) {
      $html .= '<article class="inzet-kpi"><span>'.$escape($label).'</span><strong>'.$escape($value).'</strong><small>'.$escape($note).'</small></article>';
    }
    $html .= '</div><h2>Werkstromen</h2><div class="inzet-workflows">';
    foreach ([
      ['Projectteams en planning', 'In ontwikkeling', 'Koppeling aan Office-projecten, medewerkers en leveranciers'],
      ['Urencontrole', 'In ontwikkeling', 'OnSite-registraties tegenover uren per persoon, dag en project'],
      ['Leveranciersweekstaten', $visibleSupplierWeeks === null ? 'Nog niet aangesloten' : 'Inzage beschikbaar', 'Excel-bron, opmerkingen, verschillen en beoordelingsaudit'],
      ['Factuurcontrole', 'Nog niet aangesloten', 'Alleen goedgekeurde leveranciersweekstaten komen in aanmerking'],
    ] as [$title, $state, $description]) {
      $html .= '<article class="inzet-workflow"><strong>'.$escape($title).'</strong><span>'.$escape($state).'</span><p>'.$escape($description).'</p></article>';
    }
    return $html.'</div></div>';
  }
}
