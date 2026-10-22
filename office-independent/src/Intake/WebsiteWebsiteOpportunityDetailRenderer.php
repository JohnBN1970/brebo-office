<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Read-only, HTML-escaped detail page for a native Office opportunity. */
final class WebsiteWebsiteOpportunityDetailRenderer {

  /** @param array<string,mixed> $row */
  public function render(array $row, string $basePath = '/office'): string {
    $escape = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fields = [
      'Ontvangen' => $row['created_at'] ?? '',
      'Herkomst' => $row['source'] ?? '',
      'Verkoopfase' => $row['stage'] ?? '',
      'Volgende actie' => $row['next_action'] ?? '',
      'Omschrijving' => $row['requirement_text'] ?? '',
      'Beoordeling' => !empty($row['requires_review']) ? 'Nog te beoordelen' : 'Beoordeeld',
      'Referentie' => $row['request_id'] ?? '',
    ];
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · Verkoopkans</title><style>:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f5f7fa;color:#1d2939;color-scheme:light}'
      . '*{box-sizing:border-box}body{max-width:900px;margin:0 auto;padding:clamp(1rem,4vw,3rem);line-height:1.6}main{background:#fff;border:1px solid #d9e1e8;border-radius:12px;padding:clamp(1rem,4vw,2rem)}'
      . 'h1{font-size:clamp(1.5rem,3vw,2rem);overflow-wrap:anywhere}dl{display:grid;grid-template-columns:minmax(130px,1fr) 2fr;gap:0}dt,dd{margin:0;padding:.8rem;border-bottom:1px solid #e4e8ef;overflow-wrap:anywhere}'
      . 'dt{font-weight:700;color:#475467}a{color:#14518b}a:focus-visible{outline:3px solid #327ec5;outline-offset:3px}@media(max-width:550px){dl{grid-template-columns:1fr}dt{border:0;padding-bottom:0}dd{padding-top:0}}</style></head><body><main>'
      . '<nav><a href="' . $basePath . '/crm/aanvragen">← Terug naar aanvragen</a></nav><h1>' . $escape($row['title'] ?? '') . '</h1><p>Verkoopkans · alleen lezen</p><dl>';
    foreach ($fields as $label => $value) {
      $html .= '<dt>' . $escape($label) . '</dt><dd>' . nl2br($escape($value), false) . '</dd>';
    }
    return $html . '</dl></main></body></html>';
  }
}
