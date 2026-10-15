<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Read-only draft editor markup. Submission must be handled by an authenticated,
 * CSRF-protected POST route; rendering alone never writes data.
 */
final class WebsiteCrmDraftFormRenderer {

  /** @param array<string,mixed> $opportunity
   *  @param array<string,mixed>|null $draft
   */
  public function render(array $opportunity, ?array $draft, string $csrfToken): string {
    $id = (int) ($opportunity['legacy_node_id'] ?? 0);
    if ($id < 1 || strlen($csrfToken) < 32) {
      throw new \InvalidArgumentException('Invalid CRM editor context.');
    }
    $title = $this->escape((string) ($draft['title'] ?? $opportunity['title'] ?? ''));
    $stage = $this->escape((string) ($draft['stage'] ?? $opportunity['stage'] ?? ''));
    $version = (int) ($draft['version'] ?? 0);
    if ($version < 0) {
      throw new \InvalidArgumentException('Invalid CRM draft version.');
    }
    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · CRM concept</title><style>:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1d2939;background:#f5f7fa;color-scheme:light}' 
      . '*{box-sizing:border-box}body{margin:0 auto;max-width:800px;padding:clamp(1rem,4vw,3rem);line-height:1.55}main{background:#fff;border:1px solid #d9e1e8;border-radius:12px;padding:clamp(1rem,4vw,2rem)}'
      . 'h1{font-size:clamp(1.5rem,3vw,2rem);line-height:1.2;margin:.5rem 0 1rem}p{color:#475467;max-width:65ch}a{color:#14518b;text-underline-offset:3px}'
      . 'label{display:block;font-weight:650;margin:1.4rem 0 .5rem}input:not([type=hidden]){display:block;width:100%;font:inherit;padding:.8rem;border:1px solid #98a7b9;border-radius:7px;background:#fff;color:#182435}'
      . 'input:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid #327ec5;outline-offset:3px}button{font:inherit;font-weight:650;background:#155a91;color:#fff;border:0;border-radius:7px;padding:.8rem 1.3rem;cursor:pointer;min-height:44px;margin-top:1rem}'
      . '@media(max-width:480px){main{border-radius:8px}button{width:100%}}</style></head><body><main>'
      . '<a href="/brebo-internal/crm/migration/detail?id=' . $id . '">← Terug naar verkoopkans</a>'
      . '<h1>Concept verkoopkans bewerken</h1><p>Je bewerkt een concept. De oorspronkelijke gegevens blijven ongewijzigd.</p>'
      . '<form method="post" action="/brebo-internal/crm/migration/draft">'
      . '<input type="hidden" name="id" value="' . $id . '">'
      . '<input type="hidden" name="version" value="' . $version . '">'
      . '<input type="hidden" name="csrf_token" value="' . $this->escape($csrfToken) . '">'
      . '<label for="crm-title">Titel</label><input id="crm-title" name="title" autocomplete="off" maxlength="255" aria-describedby="crm-title-hint" required value="' . $title . '">'
      . '<label for="crm-stage">Verkoopfase</label><input id="crm-stage" name="stage" autocomplete="off" maxlength="80" aria-describedby="crm-stage-hint" required value="' . $stage . '">'
      . '<button type="submit">Concept opslaan</button></form></main></body></html>';
  }

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
