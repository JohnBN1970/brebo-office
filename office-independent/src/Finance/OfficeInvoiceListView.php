<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Read-only presentation of an authorized, normalized invoice list.
 * Data access and permission checks belong to the calling application.
 */
final class OfficeInvoiceListView {
  private static function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  public static function render(array $invoices, string $direction = 'all', string $search = '', ?int $projectId = null): string {
    $direction = OfficeInvoiceDirection::filter($direction);
    $search = substr(trim($search), 0, 120);
    $rows = [];
    foreach ($invoices as $invoice) {
      if (!is_array($invoice)) continue;
      $kind = (string)($invoice['direction'] ?? '');
      if (!OfficeInvoiceDirection::isValidInvoiceDirection($kind)) continue;
      if ($direction !== 'all' && $kind !== $direction) continue;
      if ($projectId !== null && (int)($invoice['project_id'] ?? 0) !== $projectId) continue;
      $haystack = implode(' ', [(string)($invoice['number'] ?? ''), (string)($invoice['relation'] ?? ''), (string)($invoice['project_label'] ?? '')]);
      if ($search !== '' && stripos($haystack, $search) === false) continue;
      $rows[] = $invoice;
    }
    $html = '<section aria-label="Facturen"><h1>Facturen</h1><form method="get">';
    if ($projectId !== null) $html .= '<input type="hidden" name="project_id" value="' . $projectId . '">';
    $html .= '<label>Richting <select name="direction">';
    foreach (['all', 'incoming', 'outgoing'] as $option) {
      $html .= '<option value="' . $option . '"' . ($option === $direction ? ' selected' : '') . '>' . self::e(OfficeInvoiceDirection::label($option)) . '</option>';
    }
    $html .= '</select></label><label>Zoeken <input name="q" maxlength="120" value="' . self::e($search) . '"></label><button type="submit">Filteren</button></form>';
    $html .= '<table><thead><tr><th>Factuur</th><th>Richting</th><th>Relatie</th><th>Project</th><th>Status</th><th>Bedrag incl. btw</th></tr></thead><tbody>';
    foreach ($rows as $invoice) {
      $html .= '<tr><td>' . (isset($invoice['id']) && $kind === 'incoming' && (int)$invoice['id'] > 0 ? '<a href="' . self::e(\Brebo\Office\Intake\OfficeMount::route('finance/factuur', ['id'=>(int)$invoice['id']] + ($projectId === null ? [] : ['project_id'=>$projectId]))) . '">' . self::e($invoice['number'] ?? 'Concept') . '</a>' : self::e($invoice['number'] ?? 'Concept')) . '</td><td>' . self::e(OfficeInvoiceDirection::label($invoice['direction'])) . '</td><td>' . self::e($invoice['relation'] ?? '') . '</td><td>' . self::e($invoice['project_label'] ?? '') . '</td><td>' . self::e($invoice['status'] ?? '') . '</td><td>' . self::e($invoice['amount_inc_vat'] ?? '') . '</td></tr>';
    }
    $html .= '</tbody></table>';
    if ($rows === []) $html .= '<p>Geen facturen gevonden voor deze selectie.</p>';
    return $html . '</section>';
  }
}