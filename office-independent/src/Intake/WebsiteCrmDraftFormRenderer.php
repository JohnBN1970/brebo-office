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
    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>BREBO Office · CRM concept</title><style>body{font:16px system-ui,sans-serif;margin:3rem auto;max-width:720px;padding:0 1rem;color:#182435}'
      . 'label{display:block;margin:1rem 0}input{display:block;width:100%;box-sizing:border-box;padding:.65rem}button{padding:.7rem 1.2rem}</style></head><body>'
      . '<a href="/brebo-internal/crm/migration/detail?id=' . $id . '">← Terug naar verkoopkans</a>'
      . '<h1>Concept verkoopkans bewerken</h1><p>Wijzigingen worden alleen als Office-concept opgeslagen. De Drupal-bron blijft ongewijzigd.</p>'
      . '<form method="post" action="/brebo-internal/crm/migration/draft">'
      . '<input type="hidden" name="id" value="' . $id . '">'
      . '<input type="hidden" name="version" value="' . $version . '">'
      . '<input type="hidden" name="csrf_token" value="' . $this->escape($csrfToken) . '">'
      . '<label>Titel<input name="title" maxlength="255" required value="' . $title . '"></label>'
      . '<label>Verkoopfase<input name="stage" maxlength="80" required value="' . $stage . '"></label>'
      . '<button type="submit">Concept opslaan</button></form></body></html>';
  }

  private function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
