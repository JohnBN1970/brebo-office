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
    $flat = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($flat === '') {
      return [];
    }

    // Anchor on position + quantity + unit. PDF extraction may move the price
    // columns before or after descriptive text, especially on rows containing
    // extra visuals/sub-items such as ventilation grilles.
    preg_match_all(
      '/(?<!\d)(\d{3})\s+(\d+(?:[.,]\d+)?)\s+([\pL.]{1,12})(?!\pL)/u',
      $flat,
      $anchors,
      PREG_OFFSET_CAPTURE
    );

    $result = [];
    $anchorCount = count($anchors[0] ?? []);
    for ($i = 0; $i < $anchorCount; $i++) {
      $position = (string) $anchors[1][$i][0];
      $quantity = $this->decimal((string) $anchors[2][$i][0]);
      $unit = trim((string) $anchors[3][$i][0]);
      if ($quantity <= 0) {
        continue;
      }

      $anchorText = (string) $anchors[0][$i][0];
      $offset = (int) $anchors[0][$i][1];
      $afterAnchor = $offset + strlen($anchorText);
      $nextOffset = $i + 1 < $anchorCount ? (int) $anchors[0][$i + 1][1] : strlen($flat);
      $block = trim(substr($flat, $afterAnchor, max(0, $nextOffset - $afterAnchor)));
      $block = preg_split('/\b(?:Totaalbedrag\s+netto|Alle\s+prijzen\s+zijn\s+NETTO)\b/ui', $block)[0] ?? $block;

      // Currency amounts have exactly two decimals. This avoids dimensions
      // (2030, 3090), U-values (1,5) and weights (339,677).
      preg_match_all('/(?<!\d)(\d{1,3}(?:[ .]\d{3})*|\d+)\s*,\s*(\d{2})(?!\d)/u', $block, $moneyMatches, PREG_OFFSET_CAPTURE);
      if (count($moneyMatches[0] ?? []) < 2) {
        continue;
      }

      $money = [];
      foreach ($moneyMatches[0] as $moneyMatch) {
        $money[] = [
          'raw' => (string) $moneyMatch[0],
          'offset' => (int) $moneyMatch[1],
          'value' => $this->decimal((string) $moneyMatch[0]),
        ];
      }

      // Prefer an adjacent equal pair (unit price == line total for qty 1),
      // otherwise use the first plausible pair in the position block.
      $pair = NULL;
      for ($m = 0; $m < count($money) - 1; $m++) {
        $left = $money[$m];
        $right = $money[$m + 1];
        if ($left['value'] <= 0 || $right['value'] <= 0) {
          continue;
        }
        if (abs(($left['value'] * $quantity) - $right['value']) < 0.02) {
          $pair = [$left, $right];
          break;
        }
      }
      // Never guess from arbitrary monetary-looking values. Dimensions,
      // ventilation values and technical data can also have two decimals.
      if ($pair === NULL) {
        continue;
      }
      [$unitPriceMatch, $lineTotalMatch] = $pair;
      $unitPrice = (float) $unitPriceMatch['value'];
      $lineTotal = (float) $lineTotalMatch['value'];

      // Description can occur before or after the price columns in extracted PDF text.
      $descriptionSource = $block;
      if (preg_match('/\bDeurelement\b.*?(?=\b(?:Systeem|Uw-waarde|Omschrijving\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslagpakket|Ontwatering|Gewicht\s+positie|Bovenste\s+sluiter|Bander|Drukknop|Rozet|PZ-cilinder|Slot)\s*:)/ui', $block, $descriptionMatch)) {
        $description = trim((string) $descriptionMatch[0]);
      }
      else {
        // Remove the two selected prices and common table labels before using
        // the leading descriptive text as fallback.
        foreach ([$unitPriceMatch['raw'], $lineTotalMatch['raw']] as $rawAmount) {
          $descriptionSource = preg_replace('/'.preg_quote($rawAmount, '/').'/', ' ', $descriptionSource, 1) ?? $descriptionSource;
        }
        $descriptionSource = preg_replace('/\b(?:Prijs|Totaal|EUR|Positie|Aantal|Omschrijving)\b/ui', ' ', $descriptionSource) ?? $descriptionSource;
        $descriptionSource = preg_split('/\b(?:Systeem|Uw-waarde|Omschrijving\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslagpakket|Ontwatering|Gewicht\s+positie)\s*:/ui', $descriptionSource)[0] ?? $descriptionSource;
        $description = trim(preg_replace('/\s+/u', ' ', $descriptionSource) ?? '');
      }

      if ($description === '') {
        $description = 'Offertepositie ' . $position;
      }

      $result[] = [
        'position' => $position,
        'quantity' => $quantity,
        'unit' => $unit,
        'description' => mb_substr($description, 0, 500),
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'line_no' => 1,
      ];
    }

    // Recovery for positions whose columns were displaced by PDF extraction.
    // Position boundaries are authoritative; quantity/unit and price pair are
    // recovered independently inside that block.
    $seen = array_fill_keys(array_column($result, 'position'), TRUE);
    preg_match_all('/(?<!\\d)(00[1-9]|0[1-9]\\d|[1-9]\\d{2})(?!\\d)/u', $flat, $positionMatches, PREG_OFFSET_CAPTURE);
    $positionCount = count($positionMatches[0] ?? []);
    for ($p = 0; $p < $positionCount; $p++) {
      $position = (string) $positionMatches[1][$p][0];
      if (isset($seen[$position])) {
        continue;
      }

      $offset = (int) $positionMatches[0][$p][1];
      $nextOffset = $p + 1 < $positionCount ? (int) $positionMatches[0][$p + 1][1] : strlen($flat);
      $block = trim(substr($flat, $offset, max(0, $nextOffset - $offset)));

      // A real quote position must contain product semantics. This prevents
      // dates/page numbers/technical codes from becoming fake positions.
      if (!preg_match('/\\b(?:Deurelement|kozijn|deur|raam|element)\\b/ui', $block)) {
        continue;
      }

      $quantity = 1.0;
      $unit = 'Stk';
      if (preg_match('/^\\d{3}.{0,80}?\\b(\\d+(?:[.,]\\d+)?)\\s*(Stk|st|pcs?|piece|ea)\\b/ui', $block, $head)) {
        $quantity = $this->decimal($head[1]);
        $unit = $head[2];
      }

      preg_match_all('/(?<!\\d)(\\d{1,3}(?:[ .]\\d{3})*|\\d+)\\s*,\\s*(\\d{2})(?!\\d)/u', $block, $amounts, PREG_OFFSET_CAPTURE);
      $money = [];
      foreach ($amounts[0] ?? [] as $amountMatch) {
        $money[] = [
          'raw' => (string) $amountMatch[0],
          'offset' => (int) $amountMatch[1],
          'value' => $this->decimal((string) $amountMatch[0]),
        ];
      }

      $pair = NULL;
      for ($a = 0; $a < count($money); $a++) {
        for ($b = $a + 1; $b < min(count($money), $a + 5); $b++) {
          $unitPrice = (float) $money[$a]['value'];
          $lineTotal = (float) $money[$b]['value'];
          if ($unitPrice < 1 || $lineTotal < 1) {
            continue;
          }
          if (abs(($unitPrice * $quantity) - $lineTotal) < 0.02) {
            $pair = [$unitPrice, $lineTotal];
            break 2;
          }
        }
      }
      if ($pair === NULL) {
        continue;
      }

      $description = 'Offertepositie ' . $position;
      if (preg_match('/\\bDeurelement\\b.*?(?=\\b(?:Systeem|Uw-waarde|Omschrijving\\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslagpakket|Ontwatering|Gewicht\\s+positie|Bovenste\\s+sluiter|Bander|Drukknop|Rozet|PZ-cilinder|Slot)\\s*:|$)/ui', $block, $dm)) {
        $description = trim((string) $dm[0]);
      }

      $result[] = [
        'position' => $position,
        'quantity' => $quantity,
        'unit' => $unit,
        'description' => mb_substr($description, 0, 500),
        'unit_price' => $pair[0],
        'line_total' => $pair[1],
        'line_no' => 1,
      ];
      $seen[$position] = TRUE;
    }

    // Column reconstruction fallback. Some PDF extractors serialize the visual
    // price and total columns separately from the description stream. For quantity
    // one rows, the table contains the same monetary value twice. Reconstruct only
    // missing positions and only when product semantics are present.
    $seen = array_fill_keys(array_column($result, 'position'), TRUE);
    preg_match_all('/(?<!\\d)(00[1-9]|0[1-9]\\d|[1-9]\\d{2})(?!\\d)/u', $flat, $allPositions, PREG_OFFSET_CAPTURE);
    $allPositionCount = count($allPositions[0] ?? []);
    for ($p = 0; $p < $allPositionCount; $p++) {
      $position = (string) $allPositions[1][$p][0];
      if (isset($seen[$position])) {
        continue;
      }
      $offset = (int) $allPositions[0][$p][1];
      $nextOffset = $p + 1 < $allPositionCount ? (int) $allPositions[0][$p + 1][1] : strlen($flat);
      $block = trim(substr($flat, $offset, max(0, $nextOffset - $offset)));
      if (!preg_match('/\\bDeurelement\\b/ui', $block)) {
        continue;
      }

      $quantity = 1.0;
      $unit = 'Stk';
      if (preg_match('/\\b(\\d+(?:[.,]\\d+)?)\\s*(Stk|st|pcs?|piece|ea)\\b/ui', mb_substr($block, 0, 250), $qm)) {
        $quantity = $this->decimal($qm[1]);
        $unit = $qm[2];
      }

      preg_match_all('/(?<!\\d)(\\d{1,3}(?:[ .]\\d{3})*|\\d+)\\s*,\\s*(\\d{2})(?!\\d)/u', $block, $ams);
      $values = array_map(fn(string $raw): float => $this->decimal($raw), $ams[0] ?? []);

      // Find any mathematically valid pair, not necessarily adjacent. Limit the
      // search to the first monetary values of the position to avoid technical tails.
      $values = array_slice($values, 0, 16);
      $pair = NULL;
      for ($a = 0; $a < count($values); $a++) {
        if ($values[$a] < 1) {
          continue;
        }
        for ($b = $a + 1; $b < count($values); $b++) {
          if ($values[$b] < 1) {
            continue;
          }
          if (abs(($values[$a] * $quantity) - $values[$b]) < 0.02) {
            $pair = [$values[$a], $values[$b]];
            break 2;
          }
        }
      }
      if ($pair === NULL) {
        continue;
      }

      $description = 'Offertepositie ' . $position;
      if (preg_match('/\\bDeurelement\\b.*?(?=\\b(?:Systeem|Uw-waarde|Omschrijving\\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslagpakket|Ontwatering|Gewicht\\s+positie|Bovenste\\s+sluiter|Bander|Drukknop|Rozet|PZ-cilinder|Slot)\\s*:|$)/ui', $block, $dm)) {
        $description = trim((string) $dm[0]);
      }

      $result[] = [
        'position' => $position,
        'quantity' => $quantity,
        'unit' => $unit,
        'description' => mb_substr($description, 0, 500),
        'unit_price' => $pair[0],
        'line_total' => $pair[1],
        'line_no' => 1,
      ];
      $seen[$position] = TRUE;
    }

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
