<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use XMLReader;
use ZipArchive;

/**
 * Bounded XLSX row extractor for address proposals. No persistence.
 * XLSX only; legacy XLS requires an explicit conversion/extractor.
 */
final class OfficeResidentAddressXlsxReader {
  public static function read(string $path, int $maxRows = 1000): array {
    if ($maxRows < 1 || $maxRows > 10000) throw new \InvalidArgumentException('Invalid row limit');
    if (!is_file($path) || filesize($path) > 10 * 1024 * 1024) throw new \InvalidArgumentException('Invalid XLSX file');
    if (!class_exists(ZipArchive::class) || !class_exists(XMLReader::class)) throw new \RuntimeException('ZIP and XMLReader required');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new \InvalidArgumentException('Invalid XLSX archive');
    try {
      // Inspect metadata before extracting ZIP members to avoid decompression bombs.
      if ($zip->numFiles < 3 || $zip->numFiles > 200) throw new \InvalidArgumentException('Invalid XLSX package size');
      $totalExpanded = 0;
      $required = ['[Content_Types].xml'=>false, 'xl/workbook.xml'=>false, 'xl/worksheets/sheet1.xml'=>false];
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->statIndex($i);
        if (!is_array($entry)) throw new \InvalidArgumentException('Invalid ZIP entry');
        $name = (string)($entry['name'] ?? '');
        $expanded = (int)($entry['size'] ?? -1);
        $compressed = (int)($entry['comp_size'] ?? -1);
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')
          || str_contains($name, '\\') || $expanded < 0 || $compressed < 0) {
          throw new \InvalidArgumentException('Unsafe XLSX ZIP entry');
        }
        if ($expanded > 12 * 1024 * 1024) throw new \LengthException('XLSX ZIP entry too large');
        if ($expanded > 0 && $expanded > max(1, $compressed) * 200) {
          throw new \LengthException('XLSX ZIP expansion ratio exceeded');
        }
        $totalExpanded += $expanded;
        if ($totalExpanded > 24 * 1024 * 1024) throw new \LengthException('XLSX ZIP total too large');
        if (array_key_exists($name, $required)) $required[$name] = true;
      }
      if (in_array(false, $required, true)) throw new \InvalidArgumentException('Required XLSX package entries missing');
      $shared = [];
      $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
      if ($sharedXml !== false) {
        if (strlen($sharedXml) > 8 * 1024 * 1024) throw new \LengthException('Shared strings too large');
        $reader = new XMLReader();
        $reader->XML($sharedXml, null, LIBXML_NONET);
        while ($reader->read()) {
          if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            if (count($shared) >= 50000) throw new \LengthException('Too many shared strings');
            $node = $reader->expand();
            $value = '';
            foreach ($node->getElementsByTagName('t') as $t) $value .= $t->textContent;
            $shared[] = $value;
          }
        }
        $reader->close();
      }
      $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
      if ($sheet === false || strlen($sheet) > 12 * 1024 * 1024) throw new \InvalidArgumentException('First worksheet unavailable or too large');
      $reader = new XMLReader();
      $reader->XML($sheet, null, LIBXML_NONET);
      $headers = null;
      $rows = [];
      while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') continue;
        $node = $reader->expand();
        $rowNo = (int)$node->getAttribute('r');
        if ($rowNo < 1) throw new \InvalidArgumentException('Invalid worksheet row');
        $values = [];
        foreach ($node->getElementsByTagName('c') as $cell) {
          $ref = $cell->getAttribute('r');
          if (!preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/', $ref, $m)) throw new \InvalidArgumentException('Invalid cell reference');
          $col = 0;
          foreach (str_split($m[1]) as $char) $col = $col * 26 + ord($char) - 64;
          if ($col > 100) throw new \LengthException('Too many worksheet columns');
          $type = $cell->getAttribute('t');
          $v = $cell->getElementsByTagName('v')->item(0);
          if ($cell->getElementsByTagName('f')->length > 0) throw new \InvalidArgumentException('Formula cells are not accepted');
          if ($type === 'inlineStr') {
            $texts = [];
            foreach ($cell->getElementsByTagName('t') as $t) $texts[] = $t->textContent;
            $value = implode('', $texts);
          } elseif ($type === 's') {
            $idx = (int)($v?->textContent ?? '-1');
            if (!array_key_exists($idx, $shared)) throw new \InvalidArgumentException('Invalid shared string index');
            $value = $shared[$idx];
          } elseif ($type === 'e' || $type === 'f') {
            throw new \InvalidArgumentException('Unsupported formula or error cell');
          } else {
            $value = $v?->textContent ?? '';
          }
          $values[$col] = $value;
        }
        if ($headers === null) {
          $headers = $values;
          if ($headers === [] || count($headers) > 100) throw new \InvalidArgumentException('Missing XLSX headers');
          continue;
        }
        if ($values === []) continue;
        if (count($rows) >= $maxRows) throw new \LengthException('XLSX row limit exceeded');
        $row = [];
        foreach ($headers as $col => $header) {
          $header = trim((string)$header);
          if ($header === '') throw new \InvalidArgumentException('Empty XLSX header');
          if (array_key_exists($header, $row)) throw new \InvalidArgumentException('Duplicate XLSX header');
          $row[$header] = $values[$col] ?? '';
        }
        $rows[$rowNo] = $row;
      }
      $reader->close();
      return $rows;
    } finally {
      $zip->close();
    }
  }
}
