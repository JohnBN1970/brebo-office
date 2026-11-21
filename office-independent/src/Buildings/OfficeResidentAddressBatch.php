<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeResidentAddressProposal.php';

/**
 * Normalized extracted rows from CSV/XLSX/mail/PDF/Word, never raw file parsing.
 * A source-specific extractor must supply structured records with source row IDs.
 * Produces review proposals only: no database writes or resident data exports.
 */
final class OfficeResidentAddressBatch {
  public static function propose(iterable $records, string $sourceType, string $sourceId, int $maxRows = 1000): array {
    if ($maxRows < 1 || $maxRows > 10000) throw new \InvalidArgumentException('Invalid batch limit');
    $result = ['proposals' => [], 'needs_review' => [], 'errors' => []];
    $seen = [];
    $duplicateKeys = [];
    $count = 0;
    foreach ($records as $row => $record) {
      if (++$count > $maxRows) throw new \LengthException('Batch limit exceeded');
      if (!is_array($record)) {
        $result['errors'][] = ['row' => $row, 'reason' => 'invalid_record'];
        continue;
      }
      try {
        $proposal = OfficeResidentAddressProposal::fromRecord($record, $sourceType, $sourceId . ':row:' . $row);
        if ($proposal['status'] !== 'proposal') {
          $result['needs_review'][] = ['row' => $row, 'proposal' => $proposal];
          continue;
        }
        $key = $proposal['match_key'];
        if (isset($seen[$key])) {
          // Quarantine ALL rows at the same address, including the first.
          // A first-row-wins rule could otherwise silently choose a resident.
          $duplicateKeys[$key] = true;
          $result['needs_review'][] = ['row' => $row, 'reason' => 'duplicate_address', 'first_row' => $seen[$key], 'proposal' => $proposal];
          continue;
        }
        $seen[$key] = $row;
        $result['proposals'][] = ['row' => $row, 'proposal' => $proposal];
      } catch (\InvalidArgumentException $e) {
        // Avoid echoing potentially personal input or exception contents into logs.
        $result['errors'][] = ['row' => $row, 'reason' => 'invalid_fields'];
      }
    }
    if ($duplicateKeys !== []) {
      $safe = [];
      foreach ($result['proposals'] as $item) {
        if (isset($duplicateKeys[$item['proposal']['match_key']])) {
          $result['needs_review'][] = ['row' => $item['row'], 'reason' => 'duplicate_address', 'proposal' => $item['proposal']];
        } else {
          $safe[] = $item;
        }
      }
      $result['proposals'] = $safe;
    }
    return $result;
  }
}
