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
    preg_match_all('/(?<![\\d., ])(00[1-9]|0[1-9]\\d)(?![\\d.,])/u', $flat, $positionMatches, PREG_OFFSET_CAPTURE);
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
      if ($pair === NULL && abs($quantity - 1.0) < 0.0001) {
        $plausible = array_values(array_filter(
          $money,
          static fn(array $candidate): bool => (float) $candidate['value'] >= 100.0
        ));
        if ($plausible !== []) {
          usort($plausible, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
          $value = (float) $plausible[0]['value'];
          $pair = [$value, $value];
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
    preg_match_all('/(?<![\\d., ])(00[1-9]|0[1-9]\\d)(?![\\d.,])/u', $flat, $allPositions, PREG_OFFSET_CAPTURE);
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
      if ($pair === NULL && abs($quantity - 1.0) < 0.0001) {
        $plausible = array_values(array_filter(
          $values,
          static fn(float $candidate): bool => $candidate >= 100.0
        ));
        if ($plausible !== []) {
          rsort($plausible, SORT_NUMERIC);
          $pair = [(float) $plausible[0], (float) $plausible[0]];
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
      if (str_starts_with((string) $row['description'], 'Offertepositie ')) {
        $layoutDescription = $this->descriptionForPosition((string) $row['position'], $lines);
        // Some layout extractors serialize the position/price columns separately
        // from the description column. In that case there is no line starting
        // with the position near its Deurelement text. Recover by the stable
        // visual order of product descriptions, but only as a last resort.
        $layoutDescription ??= $this->descriptionForPositionOrdinal((string) $row['position'], $lines);
        if ($layoutDescription !== NULL) {
          $row['description'] = $layoutDescription;
        }
      }
      $details = $this->detailsForPosition((string) $row['position'], $lines);
      $details ??= $this->detailsForPositionOrdinal((string) $row['position'], $lines);
      $row['details'] = $details ?? '';
      $unique[$row['position']] ??= $row;
    }
    ksort($unique, SORT_NATURAL);
    return array_values($unique);
  }

  /**
   * Recover a product description from the original layout-preserving PDF lines.
   *
   * Price columns and description columns are deliberately parsed independently:
   * pdftotext -layout preserves the visual row, while flattening can interleave
   * technical values with the product description.
   *
   * @param list<string> $lines
   */
  private function descriptionForPosition(string $position, array $lines): ?string {
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
      $line = trim((string) $lines[$i]);
      if (!preg_match('/^' . preg_quote($position, '/') . '\\b/u', $line)) {
        continue;
      }

      // Search the complete logical position section. Some supplier PDFs place
      // auxiliary rows (e.g. ventilation grille / finish) between the table row
      // and the actual Deurelement description.
      $descriptionParts = [];
      $collecting = FALSE;
      for ($j = $i; $j < min($count, $i + 80); $j++) {
        $candidate = trim(preg_replace('/\\s+/u', ' ', (string) $lines[$j]) ?? '');
        if ($candidate === '') {
          continue;
        }

        if ($j > $i && (
          preg_match('/^\\d{3}\\s+\\d+(?:[.,]\\d+)?\\s+[\\pL.]{1,12}\\b/u', $candidate)
          || preg_match('/^Positie\\s+Aantal\\s+Omschrijving\\s+Prijs\\s+Totaal\\b/ui', $candidate)
        )) {
          break;
        }

        if (!$collecting) {
          $deurelementPos = mb_stripos($candidate, 'Deurelement');
          if ($deurelementPos === FALSE) {
            continue;
          }
          $candidate = mb_substr($candidate, $deurelementPos);
          $collecting = TRUE;
        }

        if (preg_match('/\\bSysteem\\s*:/ui', $candidate)) {
          $before = preg_split('/\\bSysteem\\s*:/ui', $candidate)[0] ?? '';
          if (trim($before) !== '') {
            $descriptionParts[] = trim($before);
          }
          break;
        }

        if (preg_match('/^(?:Uw-waarde|Omschrijving\\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslag|Prijs|Totaal|EUR)\\b/ui', $candidate)) {
          break;
        }

        $descriptionParts[] = $candidate;
      }

      $description = trim(preg_replace('/\\s+/u', ' ', implode(' ', $descriptionParts)) ?? '');
      if ($description !== '') {
        return mb_substr($description, 0, 500);
      }
    }
    return NULL;
  }

  /**
   * Recover a description when PDF extraction separated table columns.
   *
   * Supplier quote positions are ordered 001..nnn and the description column
   * retains that same visual order even when its position numbers are emitted
   * elsewhere in the extracted text.
   *
   * @param list<string> $lines
   */
  private function descriptionForPositionOrdinal(string $position, array $lines): ?string {
    $ordinal = (int) $position;
    if ($ordinal < 1) {
      return NULL;
    }

    $descriptions = [];
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
      $candidate = trim(preg_replace('/\\s+/u', ' ', (string) $lines[$i]) ?? '');
      $deurelementPos = mb_stripos($candidate, 'Deurelement');
      if ($deurelementPos === FALSE) {
        continue;
      }

      $parts = [];
      $candidate = mb_substr($candidate, $deurelementPos);
      for ($j = $i; $j < min($count, $i + 20); $j++) {
        if ($j > $i) {
          $candidate = trim(preg_replace('/\\s+/u', ' ', (string) $lines[$j]) ?? '');
        }
        if ($candidate === '') {
          continue;
        }

        if ($j > $i && mb_stripos($candidate, 'Deurelement') !== FALSE) {
          break;
        }
        if (preg_match('/\\bSysteem\\s*:/ui', $candidate)) {
          $before = preg_split('/\\bSysteem\\s*:/ui', $candidate)[0] ?? '';
          if (trim($before) !== '') {
            $parts[] = trim($before);
          }
          break;
        }
        if ($j > $i && preg_match('/^(?:Uw-waarde|Omschrijving\\s+deur|Kleur|Profielen|Beglazing|Beschläge|Deurbeslag|Prijs|Totaal|EUR)\\b/ui', $candidate)) {
          break;
        }
        $parts[] = $candidate;
      }

      $description = trim(preg_replace('/\\s+/u', ' ', implode(' ', $parts)) ?? '');
      if ($description !== '') {
        $descriptions[] = mb_substr($description, 0, 500);
      }
    }

    // Do not guess unless the requested ordinal actually exists.
    return $descriptions[$ordinal - 1] ?? NULL;
  }

  /**
   * Recover technical detail belonging to a quote position.
   *
   * @param list<string> $lines
   */
  private function detailsForPosition(string $position, array $lines): ?string {
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
      $line = trim((string) $lines[$i]);
      if (!preg_match('/^' . preg_quote($position, '/') . '\\b/u', $line)) {
        continue;
      }
      return $this->technicalDetailsFromSection($lines, $i, min($count, $i + 100));
    }
    return NULL;
  }

  /**
   * Fallback when PDF extraction serializes the position and description
   * columns independently. The visual order of Deurelement blocks is retained.
   *
   * @param list<string> $lines
   */
  private function detailsForPositionOrdinal(string $position, array $lines): ?string {
    $ordinal = (int) $position;
    if ($ordinal < 1) {
      return NULL;
    }

    $starts = [];
    foreach ($lines as $index => $rawLine) {
      if (mb_stripos((string) $rawLine, 'Deurelement') !== FALSE) {
        $starts[] = $index;
      }
    }
    $start = $starts[$ordinal - 1] ?? NULL;
    if ($start === NULL) {
      return NULL;
    }
    $end = $starts[$ordinal] ?? min(count($lines), $start + 100);
    return $this->technicalDetailsFromSection($lines, $start, $end);
  }

  /**
   * Keep useful technical/commercial content while excluding price-table noise.
   *
   * @param list<string> $lines
   */
  private function technicalDetailsFromSection(array $lines, int $start, int $end): ?string {
    $labels = '(?:Systeem|Uw-waarde|Omschrijving\\s+deur|Kleur(?:\\s+van\\s+het\\s+houtwerk)?|Profielen|Beglazing|Beschläge|Deurbeslag(?:pakket)?|Ontwatering|Gewicht\\s+positie|Bovenste\\s+sluiter|Bander|Drukknop|Rozet|PZ-cilinder|Slot|Ventilatierooster)';
    $details = [];
    $collectContinuation = FALSE;

    for ($i = $start; $i < $end; $i++) {
      $candidate = trim(preg_replace('/\\s+/u', ' ', (string) ($lines[$i] ?? '')) ?? '');
      if ($candidate === '') {
        continue;
      }
      if ($i > $start && preg_match('/^\\d{3}\\s+\\d+(?:[.,]\\d+)?\\s+[\\pL.]{1,12}\\b/u', $candidate)) {
        break;
      }
      if (preg_match('/^(?:Positie\\s+Aantal|Totaalbedrag\\s+netto|Alle\\s+prijzen\\s+zijn\\s+NETTO)/ui', $candidate)) {
        continue;
      }
      if (preg_match('/\\b' . $labels . '\\s*:/ui', $candidate, $match, PREG_OFFSET_CAPTURE)) {
        $offset = (int) $match[0][1];
        $details[] = trim(mb_substr($candidate, $offset));
        $collectContinuation = TRUE;
        continue;
      }
      if (preg_match('/^' . $labels . '\\b/ui', $candidate)) {
        $details[] = $candidate;
        $collectContinuation = TRUE;
        continue;
      }
      if ($collectContinuation
        && !preg_match('/^Deurelement\\b/ui', $candidate)
        && !preg_match('/^(?:Prijs|Totaal|EUR)\\b/ui', $candidate)
        && !preg_match('/^\\d{1,3}(?:[ .]\\d{3})*,\\d{2}(?:\\s+\\d{1,3}(?:[ .]\\d{3})*,\\d{2})?$/u', $candidate)
      ) {
        // Preserve short continuation text belonging to the previous technical
        // label, but reject obvious page/header noise.
        if (mb_strlen($candidate) <= 240 && !preg_match('/^(?:Pagina|Page|Offerte|Datum|Klant|Project)\\b/ui', $candidate)) {
          $details[] = $candidate;
        }
      }
    }

    $details = array_values(array_unique(array_filter(array_map('trim', $details))));
    if ($details === []) {
      return NULL;
    }
    return mb_substr(implode("\n", $details), 0, 8000);
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
