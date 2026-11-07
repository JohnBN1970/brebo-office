<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Safe HTML-only preview for review proposals. Never outputs edit controls,
 * approves records, or persists personally identifying information.
 * The caller must authorize viewing resident data before rendering.
 */
final class OfficeResidentAddressReviewPresenter {
  public static function render(array $batch, bool $canViewResidentData): string {
    if (!$canViewResidentData) throw new \RuntimeException('Not authorized to view resident proposals');
    $escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sections = [
      'proposals' => 'Nieuwe adresvoorstellen',
      'needs_review' => 'Handmatig controleren',
      'errors' => 'Afgekeurde regels',
    ];
    $html = '<section class="office-resident-address-review"><h2>Adres- en bewonersimport</h2>';
    foreach ($sections as $key => $heading) {
      $items = $batch[$key] ?? [];
      if (!is_array($items)) throw new \InvalidArgumentException('Invalid review batch');
      $html .= '<details' . ($key === 'needs_review' ? ' open' : '') . '><summary>'
        . $escape($heading) . ' (' . count($items) . ')</summary>';
      $html .= '<table><thead><tr><th>Regel</th><th>Adres</th><th>Bewoner</th><th>Reden</th></tr></thead><tbody>';
      foreach ($items as $item) {
        if (!is_array($item)) continue;
        $proposal = $item['proposal'] ?? [];
        $address = $proposal['address'] ?? [];
        $resident = $proposal['resident'] ?? [];
        $line = implode(' ', array_filter([
          $address['street'] ?? null, $address['number'] ?? null, $address['addition'] ?? null,
          $address['postcode'] ?? null, $address['city'] ?? null,
        ], static fn($part) => $part !== null && $part !== ''));
        $html .= '<tr><td>' . $escape($item['row'] ?? '') . '</td><td>' . $escape($line)
          . '</td><td>' . $escape($resident['name'] ?? '') . '</td><td>'
          . $escape($item['reason'] ?? $proposal['reason'] ?? '') . '</td></tr>';
      }
      $html .= '</tbody></table></details>';
    }
    return $html . '<p>Voorstellen zijn niet opgeslagen of goedgekeurd.</p></section>';
  }
}
