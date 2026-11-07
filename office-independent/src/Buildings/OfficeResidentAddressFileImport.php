<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeResidentAddressCsvReader.php';
require_once __DIR__ . '/OfficeResidentAddressXlsxReader.php';
require_once __DIR__ . '/OfficeResidentAddressIntakeAdapter.php';

/**
 * Local file import facade. Produces review proposals only.
 * The HTTP controller must separately enforce upload authentication, CSRF,
 * authorization, private storage and file scanning before invoking this.
 */
final class OfficeResidentAddressFileImport {
  public static function propose(string $path, string $filename, string $sourceId, int $maxRows = 1000): array {
    if (!is_file($path) || !is_readable($path)) throw new \InvalidArgumentException('Unreadable import file');
    if (trim($sourceId) === '') throw new \InvalidArgumentException('Missing document provenance');
    if ($maxRows < 1 || $maxRows > 10000) throw new \InvalidArgumentException('Invalid row limit');
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $handle = fopen($path, 'rb');
    if ($handle === false) throw new \RuntimeException('Cannot inspect import file');
    try { $signature = fread($handle, 8); } finally { fclose($handle); }
    if ($extension === 'csv') {
      if (str_starts_with($signature, "PK\x03\x04") || str_starts_with($signature, "%PDF-") || str_starts_with($signature, "\xD0\xCF\x11\xE0")) {
        throw new \InvalidArgumentException('File content does not match CSV');
      }
      if (filesize($path) > 5 * 1024 * 1024) throw new \LengthException('CSV file too large');
      $content = file_get_contents($path);
      if ($content === false) throw new \RuntimeException('Cannot read CSV');
      $firstLine = strtok($content, "\r\n");
      $delimiter = substr_count((string)$firstLine, ';') >= substr_count((string)$firstLine, ',') ? ';' : ',';
      $rows = OfficeResidentAddressCsvReader::read($content, $delimiter, $maxRows);
    } elseif ($extension === 'xlsx') {
      if (!str_starts_with($signature, "PK\x03\x04")) {
        throw new \InvalidArgumentException('File content does not match XLSX');
      }
      $rows = OfficeResidentAddressXlsxReader::read($path, $maxRows);
    } else {
      throw new \InvalidArgumentException('File type not supported by local importer');
    }
    return OfficeResidentAddressIntakeAdapter::fromExtractedRows($rows, $extension, $sourceId, $maxRows);
  }
}
