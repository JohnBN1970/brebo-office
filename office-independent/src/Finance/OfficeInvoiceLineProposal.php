<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/** Unverified line suggestions; no accounting or VAT assumptions. */
final class OfficeInvoiceLineProposal {
  public static function fromText(string $text): array {
    $candidates = [];
    foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $raw) {
      $line = trim(preg_replace('/[ \t]+/', ' ', $raw) ?? '');
      if (strlen($line) < 10 || strlen($line) > 300) continue;
      if (!preg_match('/^(.{3,150}?)\s+(\d{1,5}(?:[.,]\d{1,3})?)\s+(st|stuk|stuks|m|m2|m3|kg|uur|uren)\s+(\d{1,9}[.,]\d{2})\s+(\d{1,10}[.,]\d{2})$/iu', $line, $match)) continue;
      $quantity = (float)str_replace(',', '.', $match[2]);
      $price = (float)str_replace(',', '.', $match[4]);
      $amount = (float)str_replace(',', '.', $match[5]);
      if ($quantity <= 0 || abs($quantity * $price - $amount) > 0.03) continue;
      $candidates[] = [
        'description'=>trim($match[1]), 'quantity'=>$match[2], 'unit'=>strtolower($match[3]),
        'unit_price'=>$match[4], 'vat_rate'=>'', 'line_total'=>$match[5],
      ];
      if (count($candidates) >= 50) break;
    }
    // Some PDF extractors concatenate a product code and description across lines.
    // Recover only the description, never infer quantity, unit price or VAT.
    if ($candidates === [] && preg_match('/(?:^|\\n)[^\\n]{0,180}?\\bm\\d+\\s*(PVC\\s+hoek\\s+\\d+\\s*x\\s*\\d+\\s*x\\s*\\d+\\s*mm\\s+zwart\\s+in)\\s*\\n\\s*(lengte\\s+\\d+\\s*cm)/iu', $text, $m)) {
      $candidates[] = [
        'description'=>trim($m[1] . ' ' . $m[2]), 'quantity'=>'', 'unit'=>'',
        'unit_price'=>'', 'vat_rate'=>'', 'line_total'=>'',
      ];
    }
    return $candidates;
  }
}
