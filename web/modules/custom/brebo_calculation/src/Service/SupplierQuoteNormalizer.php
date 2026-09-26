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

    $suggested = $unique[0] ?? NULL;
    return [
      'status' => $suggested ? 'review' : 'no_price_found',
      'target' => [
        'description' => (string) ($target['description'] ?? ''),
        'quantity' => isset($target['quantity']) ? (float) $target['quantity'] : NULL,
        'unit' => (string) ($target['unit'] ?? ''),
      ],
      'candidates' => $unique,
      'suggested' => $suggested,
    ];
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
