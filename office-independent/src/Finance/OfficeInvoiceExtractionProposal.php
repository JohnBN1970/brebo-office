<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Conservative suggestions from managed extraction. Never authorizes a payment.
 * Values are hints only; the source PDF remains authoritative.
 */
final class OfficeInvoiceExtractionProposal {
  public static function fromText(string $text): array {
    $result = [];
    $flat = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    // OCR can separate every digit in a numeric value, including the comma.
    $compact = preg_replace('/(?<=\d)[ \t]+(?=[\d,.\/-])/u', '', $flat) ?? $flat;
    $compact = preg_replace('/(?<=[\d,.\/-])[ \t]+(?=\d)/u', '', $compact) ?? $compact;
    // Prefer explicitly labelled values; never infer supplier from customer address.
    if (preg_match('/(?:Factuurnr\.|Factuurnummer)\s*([0-9]{4,20})/iu', $compact, $m)) {
      $result['invoice_number'] = ['value'=>$m[1], 'confidence'=>'review'];
    } elseif (preg_match('/\b([0-9]{5,12})\s*Factuurnr\./iu', $compact, $m)) {
      $result['invoice_number'] = ['value'=>$m[1], 'confidence'=>'review'];
    }
    if (preg_match('/Totaal\s+incl\.?\s*BTW\s*[:€\s]*([0-9][0-9.,]*)/iu', $compact, $m)) {
      $result['amount_inc_vat'] = ['value'=>self::amount($m[1]), 'confidence'=>'review'];
    }
    if (preg_match('/(?:BTW-bedrag|BTW\s+bedrag)\s*[:€\s]*([0-9][0-9.,]*)/iu', $compact, $m)) {
      $result['vat_amount'] = ['value'=>self::amount($m[1]), 'confidence'=>'review'];
    }
    // Dates in document body may be delivery dates. Do not guess which is invoice date.
    return array_filter($result, static fn(array $item): bool => $item['value'] !== '');
  }

  private static function amount(string $raw): string {
    $raw = trim($raw, '.,');
    if (str_contains($raw, ',')) {
      $raw = str_replace('.', '', $raw);
      $raw = str_replace(',', '.', $raw);
    }
    return preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw) ? $raw : '';
  }
}
