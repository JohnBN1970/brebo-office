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
          // Multiple names for one address are conflicts, not silent resident updates.
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
    return $result;
  }
}
