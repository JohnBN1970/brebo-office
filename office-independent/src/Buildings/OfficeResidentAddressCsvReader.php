<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Bounded UTF-8 CSV extraction. Output is only structured rows for the
 * review-only intake adapter. Does not persist or log personal data.
 */
final class OfficeResidentAddressCsvReader {
  public static function read(string $csv, string $delimiter = ';', int $maxRows = 1000): array {
    if (!in_array($delimiter, [';', ','], true)) throw new \InvalidArgumentException('Unsupported delimiter');
    if ($maxRows < 1 || $maxRows > 10000) throw new \InvalidArgumentException('Invalid row limit');
    if (strlen($csv) > 5 * 1024 * 1024) throw new \LengthException('CSV too large');
    if (!mb_check_encoding($csv, 'UTF-8')) throw new \InvalidArgumentException('CSV must be UTF-8');
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $stream = fopen('php://temp/maxmemory:5242880', 'w+');
    if ($stream === false) throw new \RuntimeException('Cannot open CSV stream');
    try {
      fwrite($stream, $csv);
      rewind($stream);
      $header = fgetcsv($stream, 0, $delimiter, '"', '');
      if (!is_array($header) || $header === [null]) throw new \InvalidArgumentException('Missing CSV header');
      if (count($header) > 100) throw new \LengthException('Too many CSV columns');
      $header = array_map(static fn($h) => trim((string)$h), $header);
      if (in_array('', $header, true)) throw new \InvalidArgumentException('Empty CSV header');
      $keys = array_map(static fn($h) => mb_strtolower(preg_replace('/[\s-]+/u', '_', $h), 'UTF-8'), $header);
      if (count(array_unique($keys)) !== count($keys)) throw new \InvalidArgumentException('Duplicate CSV headers');
      $rows = [];
      $rowNumber = 1;
      while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        ++$rowNumber;
        if ($values === [null]) continue;
        if (count($rows) >= $maxRows) throw new \LengthException('CSV row limit exceeded');
        if (count($values) !== count($header)) throw new \InvalidArgumentException('CSV column count mismatch at row ' . $rowNumber);
        $rows[$rowNumber] = array_combine($header, $values);
      }
      return $rows;
    } finally {
      fclose($stream);
    }
  }
}
