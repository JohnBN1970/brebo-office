<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/** Read-only OCR suggestions. Never post, approve, or overwrite reviewed fields. */
final class OfficeInvoiceExtractionProposal {
  public static function fromText(string $text): array {
    $result = [];
    // Cloudflare PDF text sometimes splits numbers into individual glyphs.
    $text = str_replace("\r", '', $text);
    $compact = preg_replace('/(?<=\d)[ \t]+(?=[\d,.\/-])/u', '', $text) ?? $text;
    $compact = preg_replace('/(?<=[\d,.\/-])[ \t]+(?=\d)/u', '', $compact) ?? $compact;
    // Do not join text across newlines: that can merge unrelated columns.
    if (preg_match('/(?:Factuurnr\.|Factuurnummer)\s*([0-9]{5,12})/iu', $compact, $m)) {
      $result['invoice_number'] = self::proposal($m[1]);
    } elseif (preg_match('/(?:^|\n)\s*([0-9]{5,12})\s*Factuurnr\./iu', $compact, $m)) {
      $result['invoice_number'] = self::proposal($m[1]);
    }
    // A label-to-value relationship is trustworthy only when adjacent.
    if (preg_match('/Totaal\s+incl\.?\s*BTW\s*[:€ ]*([0-9][0-9.,]*)/iu', $compact, $m)) {
      $value = self::amount($m[1]);
      if ($value !== '') $result['amount_inc_vat'] = self::proposal($value);
    }
    if (preg_match('/(?:BTW-bedrag|BTW\s+bedrag)\s*[:€ ]*([0-9][0-9.,]*)/iu', $compact, $m)) {
      $value = self::amount($m[1]);
      if ($value !== '') $result['vat_amount'] = self::proposal($value);
    }
    // Some PDFs place the three monetary values immediately before the
    // footer labels: net, VAT, gross. Accept only when arithmetic agrees.
    if (preg_match('/(?:^|\n)\s*([0-9]{1,10},\d{2})\s*\n\s*([0-9]{1,10},\d{2})\s*\n\s*([0-9]{1,10},\d{2})\s*(?:\n|€)/u', $compact, $m)
      && stripos($compact, 'Totaal incl. BTW') !== false
      && stripos($compact, 'Totaal excl. BTW') !== false) {
      $net = self::amount($m[1]);
      $vat = self::amount($m[2]);
      $gross = self::amount($m[3]);
      if ($net !== '' && $vat !== '' && $gross !== ''
        && abs(round((float)$net + (float)$vat - (float)$gross, 2)) < 0.001) {
        $result['amount_inc_vat'] = self::proposal($gross);
        $result['vat_amount'] = self::proposal($vat);
      }
    }
    // Never guess supplier, dates, project or line items from unlabelled OCR.
    return $result;
  }

  private static function proposal(string $value): array {
    return ['value'=>$value, 'confidence'=>'review'];
  }

  private static function amount(string $raw): string {
    $raw = trim($raw, '.,');
    if (str_contains($raw, ',')) $raw = str_replace(',', '.', str_replace('.', '', $raw));
    return preg_match('/^\d{1,10}(?:\.\d{2})$/D', $raw) ? $raw : '';
  }
}
