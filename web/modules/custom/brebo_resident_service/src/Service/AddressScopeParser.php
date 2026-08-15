<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

/** Extracts Dutch street/house-number ranges from unstructured communication. */
final class AddressScopeParser {

  /**
   * @return array<int, array<string, mixed>>
   */
  public function parse(string $text): array {
    $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    $pattern = '/(?<street>[\p{L}][\p{L}\p{M}\s\.\-\'’]{2,}?)\s+(?<from>\d{1,5})\s*(?:(?:t\/?m|tot|\-|–|—)\s*(?<to>\d{1,5}))?(?:\s+(?<parity>oneven|even))?(?:[,\s]+(?<postcode>\d{4}\s?[A-Z]{2}))?(?:[,\s]+(?<city>[\p{L}][\p{L}\p{M}\s\-]{2,}))?/iu';
    preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
    $result = [];
    foreach ($matches as $match) {
      $from = (int) $match['from'];
      $to = !empty($match['to']) ? (int) $match['to'] : $from;
      $result[] = [
        'matched_text' => trim((string) $match[0]),
        'street' => trim((string) $match['street'], " ,.;:\t\n\r\0\x0B"),
        'range_from' => min($from, $to),
        'range_to' => max($from, $to),
        'range_parity' => isset($match['parity']) && $match['parity'] ? mb_strtolower((string) $match['parity']) : 'all',
        'postal_code' => isset($match['postcode']) && $match['postcode'] ? strtoupper(str_replace(' ', '', (string) $match['postcode'])) : NULL,
        'city' => isset($match['city']) && $match['city'] ? trim((string) $match['city'], " ,.;:\t\n\r\0\x0B") : NULL,
      ];
    }
    return $result;
  }
}
