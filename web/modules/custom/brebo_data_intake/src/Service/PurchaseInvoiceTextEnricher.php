<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;

/** Conservatively normalizes purchase-invoice data from extracted document text. */
final class PurchaseInvoiceTextEnricher implements IntakeEnricherInterface {

  private const LINE_PATTERN = '/^(?<description>.+?)\s+(?<quantity>\d+(?:[.,]\d+)?)\s+(?<unit>st|stuk|stuks|pcs|pc|m2|m²|m|kg|uur|uren|h|set|pak)\s+(?<unit_price>\d[\d., ]*)\s+(?<amount_ex>\d[\d., ]*)\s+(?<vat_rate>\d{1,2}(?:[.,]\d+)?)\s*%\s+(?<amount_inc>\d[\d., ]*)$/iu';

  public function supports(array $envelope): bool {
    return in_array((string) ($envelope['classification'] ?? ''), ['purchase_invoice', 'supplier_invoice'], TRUE)
      && $this->documentText($envelope) !== '';
  }

  public function enrich(array $envelope): array {
    $text = $this->documentText($envelope);
    if ($text === '') {
      return $envelope;
    }

    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $extracted = [];

    foreach ([
      'invoice_number' => ['factuurnummer', 'factuur nr', 'invoice number', 'invoice no'],
      'invoice_date' => ['factuurdatum', 'invoice date'],
      'due_date' => ['vervaldatum', 'verval datum', 'due date'],
    ] as $field => $labels) {
      if (!empty($payload[$field])) {
        continue;
      }
      $value = $field === 'invoice_number'
        ? $this->labelToken($text, $labels)
        : $this->labelDate($text, $labels);
      if ($value !== NULL) {
        $payload[$field] = $value;
        $extracted[] = $field;
      }
    }

    foreach ([
      'amount_ex_vat' => ['totaal excl. btw', 'totaal exclusief btw', 'subtotal', 'total excl. vat', 'total excluding vat'],
      'vat_amount' => ['btw-bedrag', 'btw bedrag', 'vat amount'],
      'amount_inc_vat' => ['totaal incl. btw', 'totaal inclusief btw', 'te betalen', 'amount due', 'total incl. vat', 'total including vat'],
    ] as $field => $labels) {
      if (isset($payload[$field]) && $payload[$field] !== '') {
        continue;
      }
      $value = $this->labelMoney($text, $labels);
      if ($value !== NULL) {
        $payload[$field] = $value;
        $extracted[] = $field;
      }
    }

    if (empty($payload['lines'])) {
      $lines = $this->lines($text);
      if ($lines !== [] && $this->linesBalanceWithHeader($lines, $payload)) {
        $payload['lines'] = $lines;
        $extracted[] = 'lines';
      }
    }

    $payload['extraction_provenance'] = is_array($payload['extraction_provenance'] ?? NULL)
      ? $payload['extraction_provenance']
      : [];
    $payload['extraction_provenance'][] = [
      'extractor' => 'brebo_purchase_invoice_text_v1',
      'basis' => 'already_extracted_attachment_text',
      'fields_added' => $extracted,
      'line_count' => is_array($payload['lines'] ?? NULL) ? count($payload['lines']) : 0,
      'confidence' => in_array('lines', $extracted, TRUE) ? 0.90 : 0.75,
      'canonical_truth' => FALSE,
    ];

    $envelope['payload'] = $payload;
    return $envelope;
  }

  /** @return array<int,array<string,mixed>> */
  private function lines(string $text): array {
    $rows = preg_split('/\R/u', $text) ?: [];
    $lines = [];
    $lineNumber = 1;
    foreach ($rows as $row) {
      $row = trim((string) $row);
      if ($row === '' || preg_match(self::LINE_PATTERN, $row, $match) !== 1) {
        continue;
      }
      $quantity = $this->number($match['quantity']);
      $unitPrice = $this->money($match['unit_price']);
      $amountEx = $this->money($match['amount_ex']);
      $vatRate = $this->number($match['vat_rate']);
      $amountInc = $this->money($match['amount_inc']);
      if ($quantity === NULL || $unitPrice === NULL || $amountEx === NULL || $vatRate === NULL || $amountInc === NULL) {
        continue;
      }
      if (abs(($quantity * $unitPrice) - $amountEx) > 0.05) {
        continue;
      }
      $vatAmount = round($amountInc - $amountEx, 4);
      if ($vatAmount < 0 || abs(($amountEx * ($vatRate / 100)) - $vatAmount) > 0.05) {
        continue;
      }
      $lines[] = [
        'line_number' => $lineNumber++,
        'description' => trim((string) $match['description']),
        'quantity' => $quantity,
        'unit' => trim((string) $match['unit']),
        'unit_price_ex_vat' => $unitPrice,
        'amount_ex_vat' => $amountEx,
        'vat_code' => 'RATE_' . str_replace('.', '_', rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.')),
        'vat_rate' => $vatRate,
        'vat_amount' => $vatAmount,
        'amount_inc_vat' => $amountInc,
      ];
    }
    return $lines;
  }

  /** @param array<int,array<string,mixed>> $lines */
  private function linesBalanceWithHeader(array $lines, array $payload): bool {
    foreach (['amount_ex_vat', 'vat_amount', 'amount_inc_vat'] as $field) {
      if (!isset($payload[$field]) || !is_numeric($payload[$field])) {
        return FALSE;
      }
    }
    $sumEx = array_sum(array_map(static fn(array $line): float => (float) $line['amount_ex_vat'], $lines));
    $sumVat = array_sum(array_map(static fn(array $line): float => (float) $line['vat_amount'], $lines));
    $sumInc = array_sum(array_map(static fn(array $line): float => (float) $line['amount_inc_vat'], $lines));
    return abs($sumEx - (float) $payload['amount_ex_vat']) <= 0.05
      && abs($sumVat - (float) $payload['vat_amount']) <= 0.05
      && abs($sumInc - (float) $payload['amount_inc_vat']) <= 0.05;
  }

  private function documentText(array $envelope): string {
    $parts = [];
    foreach ((array) ($envelope['attachments'] ?? []) as $attachment) {
      if (!is_array($attachment)) {
        continue;
      }
      $text = trim((string) ($attachment['extracted_text'] ?? ''));
      if ($text !== '') {
        $parts[] = $text;
      }
      foreach ((array) ($attachment['extracted_pages'] ?? []) as $page) {
        if (is_array($page) && trim((string) ($page['text'] ?? '')) !== '') {
          $parts[] = trim((string) $page['text']);
        }
      }
      if (($attachment['type'] ?? '') === 'mail_attachment_evidence') {
        $context = trim((string) ($attachment['evidence']['context_text'] ?? ''));
        if ($context !== '') {
          $parts[] = $context;
        }
      }
    }
    return trim(implode("\n", array_unique($parts)));
  }

  private function labelToken(string $text, array $labels): ?string {
    foreach ($labels as $label) {
      if (preg_match('/' . preg_quote($label, '/') . '\s*[:#-]?\s*([A-Z0-9][A-Z0-9._\/-]{2,60})/iu', $text, $m) === 1) {
        return trim((string) $m[1]);
      }
    }
    return NULL;
  }

  private function labelDate(string $text, array $labels): ?string {
    foreach ($labels as $label) {
      if (preg_match('/' . preg_quote($label, '/') . '\s*[:#-]?\s*([0-3]?\d)[-.\/]([01]?\d)[-.\/](20\d{2})/iu', $text, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
      }
      if (preg_match('/' . preg_quote($label, '/') . '\s*[:#-]?\s*(20\d{2})[-\/]([01]?\d)[-\/]([0-3]?\d)/iu', $text, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
      }
    }
    return NULL;
  }

  private function labelMoney(string $text, array $labels): ?float {
    foreach ($labels as $label) {
      if (preg_match('/' . preg_quote($label, '/') . '\s*[:€ ]*([0-9][0-9. ]*,[0-9]{2}|[0-9][0-9, ]*\.[0-9]{2})/iu', $text, $m) === 1) {
        return $this->money((string) $m[1]);
      }
    }
    return NULL;
  }

  private function money(string $value): ?float {
    $value = str_replace(' ', '', trim($value));
    if ($value === '') {
      return NULL;
    }
    if (str_contains($value, ',') && str_contains($value, '.')) {
      $value = str_replace('.', '', $value);
    }
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? round((float) $value, 4) : NULL;
  }

  private function number(string $value): ?float {
    $value = str_replace(',', '.', trim($value));
    return is_numeric($value) ? round((float) $value, 4) : NULL;
  }

}
