<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Escaped, read-only independent CRM migration workbench HTML. */
final class WebsiteCrmLegacyWorkbenchRenderer {

  public function render(WebsiteCrmLegacyReadRepository $repository): string {
    $sections = [
      'Verkoopkansen' => $repository->opportunities(),
      'Contactpersonen' => $repository->parties('brebo_contact'),
      'Organisaties' => $repository->parties('brebo_organization'),
    ];
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · CRM migratiecontrole</title><style>body{font:15px system-ui,sans-serif;margin:3rem auto;max-width:1100px;padding:0 1rem;color:#182435;background:#f7f9fc}'
      . 'h1{font-size:1.8rem}section{background:white;padding:1.4rem;margin:1.2rem 0;border-radius:12px;box-shadow:0 2px 10px #dbe2eb}'
      . 'table{border-collapse:collapse;width:100%}td,th{text-align:left;border-bottom:1px solid #e2e8f0;padding:.65rem}th{color:#42546b}'
      . 'p{color:#52657b}</style></head><body><h1>CRM · migratiecontrole</h1>'
      . '<p>Alleen-lezen. Gegevens uit de zelfstandige Office-migratiedatabase; geen Drupal-verbinding. Maximaal 50 records per onderdeel.</p>';
    foreach ($sections as $label => $rows) {
      $html .= '<section><h2>' . $this->escape($label) . '</h2>';
      if ($rows === []) {
        $html .= '<p>Geen records aanwezig.</p></section>';
        continue;
      }
      $columns = array_keys($rows[0]);
      $html .= '<table><thead><tr>';
      foreach ($columns as $column) {
        $html .= '<th>' . $this->escape((string) $column) . '</th>';
      }
      $html .= '</tr></thead><tbody>';
      foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $column) {
          $html .= '<td>' . $this->escape((string) ($row[$column] ?? '')) . '</td>';
        }
        $html .= '</tr>';
      }
      $html .= '</tbody></table></section>';
    }
    return $html . '</body></html>';
  }

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
