<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Server-rendered, escaped read-only review fragment for Office.
 * Caller must enforce authentication and supplier read permission.
 * No POST endpoint or approval side effects are exposed.
 */
final class OnSiteSupplierWeekReviewPresenter {
  /** @param array<string,mixed> $workbench */
  public function render(array $workbench): string {
    $escape = static fn(mixed $value): string =>
      htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $supplier = (int)($workbench['supplier_id'] ?? 0);
    if ($supplier < 1) throw new \InvalidArgumentException('Supplier required.');
    $html = '<section class="office-inzet-week" aria-label="Leveranciersurenstaat">'
      .'<h2>Leverancier '.$escape($supplier).' — urencontrole</h2>'
      .'<p>Status: '.$escape($workbench['status'] ?? 'requires_review').'</p>'
      .'<p>Afwijkende dagregels: '.$escape($workbench['difference_count'] ?? 0)
      .'; openstaande controlepunten: '.$escape($workbench['issue_count'] ?? 0).'</p>'
      .'<p>OnSite: '.$escape($workbench['onsite_total_minutes'] ?? 0).' minuten; '
      .'leverancier: '.$escape($workbench['supplier_total_minutes'] ?? 0).' minuten</p>'
      .'<table><thead><tr><th>Datum</th><th>Medewerker</th><th>Project</th>'
      .'<th>OnSite (min)</th><th>Leverancier (min)</th><th>Verschil (min)</th></tr></thead><tbody>';
    foreach (($workbench['day_lines'] ?? []) as $line) {
      $html .= '<tr><td>'.$escape($line['date'] ?? '').'</td>'
        .'<td>'.$escape($line['person_id'] ?? '').'</td>'
        .'<td>'.$escape($line['project_id'] ?? '').'</td>'
        .'<td>'.$escape($line['onsite_minutes'] ?? '').'</td>'
        .'<td>'.$escape($line['supplier_minutes'] ?? '').'</td>'
        .'<td>'.$escape($line['difference_minutes'] ?? '').'</td></tr>';
    }
    $html .= '</tbody></table><h3>Te beoordelen bronregels</h3><ul>';
    foreach (($workbench['issues'] ?? []) as $issue) {
      $html .= '<li>'.$escape($issue['sheet'] ?? '').' / '.$escape($issue['cell'] ?? '')
        .' — '.$escape($issue['reason'] ?? '').'</li>';
    }
    $html .= '</ul><p>'.(($workbench['can_approve'] ?? false) === true
      ? 'Uren sluiten aan; expliciete goedkeuring vereist.'
      : 'Goedkeuring geblokkeerd totdat alle controlepunten zijn opgelost.')
      .'</p></section>';
    return $html;
  }
}
