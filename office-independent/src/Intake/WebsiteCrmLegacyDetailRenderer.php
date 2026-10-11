<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Read-only, escaped opportunity details from independent CRM staging. */
final class WebsiteCrmLegacyDetailRenderer {

  public function render(WebsiteCrmLegacyReadRepository $repository, int $legacyId): ?string {
    $opportunity = $repository->opportunity($legacyId);
    if ($opportunity === null) {
      return null;
    }
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · CRM kans</title><style>body{font:15px system-ui,sans-serif;margin:3rem auto;max-width:960px;padding:0 1rem;color:#182435;background:#f7f9fc}'
      . 'section{background:#fff;border-radius:12px;padding:1.5rem;margin:1rem 0}table{border-collapse:collapse;width:100%}'
      . 'td,th{text-align:left;padding:.65rem;border-bottom:1px solid #e2e8f0}</style></head><body>'
      . '<h1>' . $this->escape((string) $opportunity['title']) . '</h1><p>CRM migratiecontrole · alleen-lezen</p>';
    foreach ([
      'Kans' => [$opportunity],
      'Relaties' => $repository->relations($legacyId),
      'Fasehistorie' => $repository->history($legacyId),
    ] as $label => $rows) {
      $html .= '<section><h2>' . $this->escape($label) . '</h2>';
      if ($rows === []) {
        $html .= '<p>Geen gegevens aanwezig.</p></section>';
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
