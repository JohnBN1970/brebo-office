<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Accessible, escaped CRM overview for newly received Office website leads. */
final class WebsiteWebsiteOpportunityListRenderer {

  /** @param list<array<string,mixed>> $rows */
  public function render(array $rows, int $page = 1): string {
    if ($page < 1 || $page > 20001) {
      throw new \InvalidArgumentException('Invalid CRM page number.');
    }
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · Nieuwe aanvragen</title><style>:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1d2939;background:#f5f7fa;color-scheme:light}'
      . '*{box-sizing:border-box}body{margin:0 auto;max-width:1200px;padding:clamp(1rem,3vw,2.5rem);line-height:1.5}h1{font-size:clamp(1.5rem,3vw,2rem);margin:.25rem 0}p{color:#475467}'
      . 'main{background:#fff;border:1px solid #d9e1e8;border-radius:12px;padding:clamp(1rem,3vw,1.5rem)}.table-scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:720px}'
      . 'th,td{text-align:left;padding:.8rem;border-bottom:1px solid #e4e8ef;vertical-align:top}th{background:#f2f5f9;color:#344054;font-weight:700}td:first-child{font-weight:650}'
      . '.status{display:inline-block;border-radius:6px;background:#edf2f8;padding:.2rem .55rem;color:#344054}tr:hover td{background:#f9fbfd}.pagination{display:flex;gap:1rem;align-items:center;justify-content:flex-end;flex-wrap:wrap;margin-top:1.2rem}.pagination a{display:inline-block;padding:.6rem .8rem;border:1px solid #b9c8d8;border-radius:7px}a{color:#14518b;text-underline-offset:3px}a:focus-visible{outline:3px solid #327ec5;outline-offset:3px}'
      . '@media(max-width:700px){body{padding:1rem}main{padding:1rem}}</style></head><body><main><h1>Nieuwe aanvragen</h1>'
      . '<p>Nieuwe verkoopkansen uit de website. Controleer de gegevens en bepaal de volgende actie.</p>';
    if ($rows === []) {
      return $html . '<p role="status">Er zijn nog geen aanvragen ontvangen.</p></main></body></html>';
    }
    $html .= '<div class="table-scroll" role="region" aria-label="Overzicht nieuwe aanvragen" tabindex="0"><table><thead><tr>'
      . '<th scope="col">Aanvraag</th><th scope="col">Ontvangen</th><th scope="col">Bron</th><th scope="col">Fase</th><th scope="col">Volgende actie</th><th scope="col">Controle</th>'
      . '</tr></thead><tbody>';
    foreach ($rows as $row) {
      $id = (int) ($row['id'] ?? 0);
      $title = $this->escape((string) ($row['title'] ?? ''));
      $titleCell = $id > 0 ? '<a href="/office/crm/aanvragen/detail?id=' . $id . '">' . $title . '</a>' : $title;
      $html .= '<tr><td>' . $titleCell
        . '</td><td>' . $this->escape((string) ($row['created_at'] ?? ''))
        . '</td><td>' . $this->escape((string) ($row['source'] ?? ''))
        . '</td><td><span class="status">' . $this->escape((string) ($row['stage'] ?? ''))
        . '</span></td><td>' . $this->escape((string) ($row['next_action'] ?? ''))
        . '</td><td>' . (!empty($row['requires_review']) ? 'Te beoordelen' : 'Beoordeeld') . '</td></tr>';
    }
    $html .= '</tbody></table></div><nav aria-label="Paginanavigatie" class="pagination">';
    if ($page > 1) {
      $html .= '<a href="/office/crm/aanvragen?page=' . ($page - 1) . '">← Vorige</a>';
    }
    $html .= '<span>Pagina ' . $page . '</span>';
    if (count($rows) === 50 && $page < 20001) {
      $html .= '<a href="/office/crm/aanvragen?page=' . ($page + 1) . '">Volgende →</a>';
    }
    return $html . '</nav></main></body></html>';
  }

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
