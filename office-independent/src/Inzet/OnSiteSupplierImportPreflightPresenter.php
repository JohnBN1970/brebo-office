<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Escaped read-only Office preview, not an approval or import endpoint. */
final class OnSiteSupplierImportPreflightPresenter {
  /** @param array<string,mixed> $report */
  public static function render(array $report): string {
    $escape = static fn(mixed $value): string =>
      htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-import-preflight"><h2>Urenverantwoording: importcontrole</h2>'
      . '<p>Alle gegevens zijn bronvermeldingen. Geen medewerkers of projecten zijn automatisch gekoppeld.</p>'
      . '<table><tbody>';
    foreach ([
      'Dagregels' => 'row_count',
      'Naamvermeldingen' => 'person_label_count',
      'Projectvermeldingen' => 'project_label_count',
      'Waarschuwingen' => 'warning_count',
    ] as $label => $key) {
      $html .= '<tr><th>'.$escape($label).'</th><td>'.(int)($report[$key] ?? 0).'</td></tr>';
    }
    $html .= '</tbody></table><h3>Te beoordelen bronvermeldingen</h3>';
    foreach (['person_labels'=>'Personen', 'project_labels'=>'Projecten'] as $key=>$label) {
      $html .= '<details><summary>'.$escape($label).'</summary><ul>';
      foreach (array_slice((array)($report[$key] ?? []), 0, 200) as $value) {
        $html .= '<li>'.$escape($value).'</li>';
      }
      $html .= '</ul></details>';
    }
    $html .= '<p>Importstatus: beoordeling vereist. Geen uren- of factuurgoedkeuring.</p></section>';
    return $html;
  }
}
