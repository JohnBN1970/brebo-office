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
    if ($extension === 'csv') {
      if (filesize($path) > 5 * 1024 * 1024) throw new \LengthException('CSV file too large');
      $content = file_get_contents($path);
      if ($content === false) throw new \RuntimeException('Cannot read CSV');
      $firstLine = strtok($content, "\r\n");
      $delimiter = substr_count((string)$firstLine, ';') >= substr_count((string)$firstLine, ',') ? ';' : ',';
      $rows = OfficeResidentAddressCsvReader::read($content, $delimiter, $maxRows);
    } elseif ($extension === 'xlsx') {
      $rows = OfficeResidentAddressXlsxReader::read($path, $maxRows);
    } else {
      throw new \InvalidArgumentException('File type not supported by local importer');
    }
    return OfficeResidentAddressIntakeAdapter::fromExtractedRows($rows, $extension, $sourceId, $maxRows);
  }
}
