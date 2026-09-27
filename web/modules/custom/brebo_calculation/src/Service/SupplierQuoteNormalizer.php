<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

/**
 * Builds human-reviewable supplier quote price proposals from extracted text.
 */
final class SupplierQuoteNormalizer {

  /**
   * @param array{description?:string,quantity?:float|int|null,unit?:string} $target
   * @return array<string,mixed>
   */
  public function normalize(string $text, array $target = []): array {
    $text = trim($text);
    if ($text === '') {
      return ['status' => 'no_text', 'target' => $target, 'candidates' => [], 'suggested' => NULL];
    }

    $description = mb_strtolower(trim((string) ($target['description'] ?? '')));
    $targetTokens = $this->tokens($description);
    $lines = preg_split('/\R/u', $text) ?: [];
    $candidates = [];

    foreach ($lines as $index => $rawLine) {
      $line = trim(preg_replace('/\s+/u', ' ', (string) $rawLine) ?? '');
      if ($line === '') {
        continue;
      }

      $amounts = $this->amounts($line);
      if ($amounts === []) {
        continue;
      }

      $lineLower = mb_strtolower($line);
      $lineTokens = $this->tokens($lineLower);
      $overlap = count(array_intersect($targetTokens, $lineTokens));
      $score = $overlap * 12;
      if (preg_match('/\b(netto|net price|eenheidsprijs|prijs\/eh|per stuk|per st|unit price)\b/ui', $line)) {
        $score += 18;
      }
      if (preg_match('/\b(offerte|aanbieding|totaal|subtotaal|bedrag)\b/ui', $line)) {
        $score += 4;
      }
      if ($description !== '' && str_contains($lineLower, $description)) {
        $score += 25;
      }

      foreach ($amounts as $position => $amount) {
        if ($amount < 0) {
          continue;
        }
        $candidateScore = $score + ($position === count($amounts) - 1 ? 2 : 0);
        $candidates[] = [
          'value' => $amount,
          'score' => $candidateScore,
          'line_no' => $index + 1,
          'text' => mb_substr($line, 0, 500),
        ];
      }
    }

    usort($candidates, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($a['line_no'] <=> $b['line_no']));
    $seen = [];
    $unique = [];
    foreach ($candidates as $candidate) {
      $key = number_format((float) $candidate['value'], 4, '.', '');
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $unique[] = $candidate;
      if (count($unique) >= 12) {
        break;
      }
    }

    $quoteLines = $this->quoteLines($text, $lines);
    $classification = $this->classifyScope($text, $quoteLines);
    $suggested = $unique[0] ?? NULL;
    return [
      'status' => $quoteLines !== [] ? 'structured_review' : ($suggested ? 'review' : 'no_price_found'),
      'target' => [
        'description' => (string) ($target['description'] ?? ''),
        'quantity' => isset($target['quantity']) ? (float) $target['quantity'] : NULL,
        'unit' => (string) ($target['unit'] ?? ''),
      ],
      'classification' => $classification,
      'lines' => $quoteLines,
      'candidates' => $unique,
      'suggested' => $suggested,
    ];
  }

  /** @param list<array<string,mixed>> $quoteLines
   *  @return array{discipline:string,element:string,material:string,type:string,confidence:float}|null
   */
  private function classifyScope(string $text, array $quoteLines): ?array {
    $haystack = mb_strtolower($text);
    $score = 0;
    foreach (['deurelement', 'jansen janisol', 'jansen economy', 'beglazing', 'profielen:', 'deurbeslag', 'aanlaspaum'] as $needle) {
      if (str_contains($haystack, $needle)) {
        $score++;
      }
    }
    if ($quoteLines !== [] && $score >= 3) {
      return [
        'discipline' => 'gevel_en_openingen',
        'element' => 'kozijn',
        'material' => 'staal',
        'type' => 'kozijn_deur',
        'confidence' => min(0.99, 0.70 + ($score * 0.04)),
      ];
    }
    return NULL;
  }

  /** @param list<string> $lines
   *  @return list<array{position:string,quantity:float,unit:string,description:string,unit_price:float,line_total:float,line_no:int}>
   */
  private function quoteLines(string $text, array $lines): array {
    $result = [];
    $flat = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($flat === '') {
      return [];
    }

    // Match quote rows even when PDF extraction split table columns across lines.
    // Example: 001 1 Stk 9 674,17 9 674,17
    preg_match_all(
      '/(?<!\d)(\d{3})\s+(\d+(?:[.,]\d+)?)\s+([\pL.]{1,12})\s+([\d .]+,\d{2})\s+([\d .]+,\d{2})(?!\d)/u',
      $flat,
      $matches,
      PREG_OFFSET_CAPTURE
    );

    $count = count($matches[0] ?? []);
    for ($i = 0; $i < $count; $i++) {
      $position = (string) $matches[1][$i][0];
      $quantity = $this->decimal((string) $matches[2][$i][0]);
      $unit = trim((string) $matches[3][$i][0]);
      $unitPrice = $this->decimal((string) $matches[4][$i][0]);
      $lineTotal = $this->decimal((string) $matches[5][$i][0]);
      if ($quantity <= 0 || $unitPrice < 0 || $lineTotal < 0) {
        continue;
      }

      $matchText = (string) $matches[0][$i][0];
      $offset = (int) $matches[0][$i][1];
      $after = $offset + strlen($matchText);
      $nextOffset = $i + 1 < $count ? (int) $matches[0][$i + 1][1] : strlen($flat);
      $segment = trim(substr($flat, $after, max(0, $nextOffset - $after)));

      // Cut trailing document totals/conditions from the final position.
      $segment = preg_split('/\b(?:Totaalbedrag\s+netto|Alle\s+prijzen\s+zijn\s+NETTO|Positie\s+Aantal\s+Omschrijving\s+Prijs\s+Totaal)\b/ui', $segment)[0] ?? $segment;

      // Prefer the product heading and first descriptive continuation, not technical tail fields.
      $segment = preg_split('/\b(?:Systeem|Uw-waarde|Omschrijving\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslagpakket|Ontwatering|Gewicht\s+positie|Bovenste\s+sluiter|Bander|Drukknop|Rozet|PZ-cilinder|Slot)\s*:/ui', $segment)[0] ?? $segment;
      $segment = trim(preg_replace('/\s+/u', ' ', $segment) ?? '');

      if ($segment === '') {
        $segment = 'Offertepositie ' . $position;
      }

      // Approximate source line for review/debugging.
      $prefix = substr($flat, 0, $offset);
      $lineNo = max(1, substr_count($prefix, "\n") + 1);

      $result[] = [
        'position' => $position,
        'quantity' => $quantity,
        'unit' => $unit,
        'description' => mb_substr($segment, 0, 500),
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'line_no' => $lineNo,
      ];
    }

    // Fallback to line-wise detection for extractors that preserve table rows cleanly.
    if ($result === []) {
      foreach ($lines as $index => $rawLine) {
        $line = trim(preg_replace('/\s+/u', ' ', (string) $rawLine) ?? '');
        if (!preg_match('/^(\d{3})\s+(\d+(?:[.,]\d+)?)\s+([\pL.]+)\s+([\d .]+,\d{2})\s+([\d .]+,\d{2})$/u', $line, $m)) {
          continue;
        }
        $result[] = [
          'position' => $m[1],
          'quantity' => $this->decimal($m[2]),
          'unit' => trim($m[3]),
          'description' => 'Offertepositie ' . $m[1],
          'unit_price' => $this->decimal($m[4]),
          'line_total' => $this->decimal($m[5]),
          'line_no' => $index + 1,
        ];
      }
    }

    // De-duplicate positions; first complete occurrence wins.
    $unique = [];
    foreach ($result as $row) {
      $unique[$row['position']] ??= $row;
    }
    ksort($unique, SORT_NATURAL);
    return array_values($unique);
  }


  private function decimal(string $raw): float {
    $normalized = str_replace([' ', '.'], '', trim($raw));
    $normalized = str_replace(',', '.', $normalized);
    return is_numeric($normalized) ? (float) $normalized : 0.0;
  }

  /** @return list<string> */
  private function tokens(string $value): array {
    $parts = preg_split('/[^\pL\pN]+/u', mb_strtolower($value)) ?: [];
    $stop = ['de','het','een','en','van','voor','met','op','te','in','per','incl','excl'];
    return array_values(array_unique(array_filter($parts, static fn(string $token): bool => mb_strlen($token) >= 3 && !in_array($token, $stop, TRUE))));
  }

  /** @return list<float> */
  private function amounts(string $line): array {
    preg_match_all('/(?<![\d])(?:€\s*)?(-?\d{1,3}(?:[\.\s]\d{3})*(?:,\d{2,4})|-?\d+(?:[\.,]\d{2,4}))(?![\d])/u', $line, $matches);
    $values = [];
    foreach ($matches[1] ?? [] as $raw) {
      $normalized = str_replace([' ', '.'], '', (string) $raw);
      $normalized = str_replace(',', '.', $normalized);
      if (is_numeric($normalized)) {
        $values[] = (float) $normalized;
      }
    }
    return $values;
  }

}
