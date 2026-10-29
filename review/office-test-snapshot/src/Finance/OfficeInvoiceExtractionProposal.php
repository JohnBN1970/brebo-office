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
    // OCR often emits the net/VAT/gross footer as separate lines, with the
    // gross amount split into glyphs. Only propose values when all three
    // amounts reconcile and the invoice total labels occur in the document.
    if (stripos($compact, 'Totaal excl. BTW') !== false
      && stripos($compact, 'Totaal incl. BTW') !== false) {
      $lines = preg_split('/\\n/u', $text) ?: [];
      foreach ($lines as $i => $line) {
        if (!preg_match('/^\\s*([0-9]{1,10},[0-9]{2})\\s*$/u', $line, $netMatch)) continue;
        $vatLine = $lines[$i + 1] ?? '';
        $grossLine = $lines[$i + 2] ?? '';
        if (!preg_match('/^\\s*([0-9]{1,10},[0-9]{2})\\s*$/u', $vatLine, $vatMatch)) continue;
        // Join spaces only within this candidate monetary value.
        $grossLine = preg_replace('/\\s+/u', '', $grossLine) ?? '';
        if (!preg_match('/^([0-9]{1,10},[0-9]{2})$/u', $grossLine, $grossMatch)) continue;
        $net = self::amount($netMatch[1]);
        $vat = self::amount($vatMatch[1]);
        $gross = self::amount($grossMatch[1]);
        if ($net !== '' && $vat !== '' && $gross !== ''
          && abs(round((float)$net + (float)$vat - (float)$gross, 2)) < 0.001) {
          $result['amount_inc_vat'] = self::proposal($gross);
          $result['vat_amount'] = self::proposal($vat);
          break;
        }
      }
    }
    // Managed extraction can flatten all footer lines into one paragraph.
    // Look for the explicit payment reference, then a net/VAT/gross triplet.
    // This is a proposal only, and requires exact cent reconciliation.
    if (!isset($result['amount_inc_vat'])
      && stripos($compact, 'Totaal excl. BTW') !== false
      && stripos($compact, 'Totaal incl. BTW') !== false
      && preg_match('/Betaling binnen\\s+\\d+\\s+dagen\\s+o\\.v\\.v\\.:\\s*\\d+\\/\\d+\\s+([0-9]{1,10},[0-9]{2})\\s+([0-9]{1,10},[0-9]{2})\\s+((?:[0-9]\\s*){1,10},\\s*[0-9]\\s*[0-9])(?=\\s*€)/iu', $compact, $m)) {
      $net = self::amount($m[1]);
      $vat = self::amount($m[2]);
      $gross = self::amount(preg_replace('/\\s+/u', '', $m[3]) ?? '');
      if ($net !== '' && $vat !== '' && $gross !== ''
        && abs(round((float)$net + (float)$vat - (float)$gross, 2)) < 0.001) {
        $result['amount_inc_vat'] = self::proposal($gross);
        $result['vat_amount'] = self::proposal($vat);
      }
    }
    // The managed PDF extractor may concatenate the entire page into one
    // line. Match the monetary triplet after the payment reference directly
    // in original text, not after digit-space normalization (which can merge
    // adjacent monetary fields).
    if (!isset($result['amount_inc_vat'])
      && stripos($text, 'Totaal incl. BTW') !== false
      && stripos($text, 'Totaal excl. BTW') !== false
      && preg_match('/Betaling binnen\\s+\\d+\\s+dagen\\s+o\\.v\\.v\\.:\\s*\\d+\\/\\d+\\s+([0-9]{1,10},[0-9]{2})\\s+([0-9]{1,10},[0-9]{2})\\s+((?:[0-9]\\s*){1,10},\\s*[0-9]\\s*[0-9])\\s*€/iu', $text, $m)) {
      $net = self::amount($m[1]);
      $vat = self::amount($m[2]);
      $gross = self::amount(preg_replace('/\\s+/u', '', $m[3]) ?? '');
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
