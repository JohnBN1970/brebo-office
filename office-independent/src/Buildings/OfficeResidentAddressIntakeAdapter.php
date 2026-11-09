<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeResidentAddressColumnMapper.php';
require_once __DIR__ . '/OfficeResidentAddressBatch.php';

/**
 * Shared adapter for structured records emitted by Office mail/document/CSV/XLSX
 * extractors. No raw file parsing, DB writes or automatic identity decisions.
 */
final class OfficeResidentAddressIntakeAdapter {
  public static function fromExtractedRows(iterable $rows, string $sourceType, string $sourceId, int $maxRows = 1000): array {
    if (!in_array($sourceType, ['email', 'xlsx', 'csv', 'pdf', 'docx'], true)) {
      throw new \InvalidArgumentException('Unsupported intake source');
    }
    if (trim($sourceId) === '') throw new \InvalidArgumentException('Source ID required');
    if ($maxRows < 1 || $maxRows > 10000) throw new \InvalidArgumentException('Invalid batch limit');
    $normalized = [];
    $errors = [];
    $count = 0;
    foreach ($rows as $rowId => $row) {
      if (++$count > $maxRows) throw new \LengthException('Batch limit exceeded');
      if (!is_array($row)) {
        $errors[] = ['row' => $rowId, 'reason' => 'invalid_record'];
        continue;
      }
      try {
        $normalized[$rowId] = OfficeResidentAddressColumnMapper::map($row);
      } catch (\InvalidArgumentException $e) {
        $errors[] = ['row' => $rowId, 'reason' => 'ambiguous_columns'];
      }
    }
    $result = OfficeResidentAddressBatch::propose($normalized, $sourceType, $sourceId, $maxRows);
    $result['errors'] = array_merge($errors, $result['errors']);
    return $result;
  }
}
