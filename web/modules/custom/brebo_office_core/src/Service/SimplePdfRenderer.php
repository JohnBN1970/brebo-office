<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Lightweight dependency-free PDF renderer for BREBO output previews. */
final class SimplePdfRenderer {

  /**
   * @param string[] $lines
   */
  public function render(string $title, array $lines, string $watermark = ''): string {
    $pageChunks = array_chunk($lines, 42);
    if ($pageChunks === []) {
      $pageChunks = [[]];
    }

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $pageObjectIds = [];
    $contentObjectIds = [];
    $nextId = 4;
    foreach ($pageChunks as $_) {
      $pageObjectIds[] = $nextId++;
      $contentObjectIds[] = $nextId++;
    }
    $objects[2] = '<< /Type /Pages /Count ' . count($pageChunks) . ' /Kids [' . implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageObjectIds)) . '] >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    foreach ($pageChunks as $index => $chunk) {
      $commands = [];
      $commands[] = 'BT /F1 17 Tf 50 795 Td (' . $this->escape($title) . ') Tj ET';
      if ($watermark !== '') {
        $commands[] = 'q 0.88 g BT /F1 34 Tf 120 420 Td (' . $this->escape($watermark) . ') Tj ET Q';
      }
      $y = 760;
      foreach ($chunk as $line) {
        foreach ($this->wrap((string) $line, 92) as $wrapped) {
          $commands[] = 'BT /F1 10 Tf 50 ' . $y . ' Td (' . $this->escape($wrapped) . ') Tj ET';
          $y -= 16;
        }
      }
      $commands[] = 'BT /F1 8 Tf 50 28 Td (Pagina ' . ($index + 1) . ' van ' . count($pageChunks) . ') Tj ET';
      $stream = implode("\n", $commands);
      $contentId = $contentObjectIds[$index];
      $pageId = $pageObjectIds[$index];
      $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
      $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
      $offsets[$id] = strlen($pdf);
      $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $maxId; $id++) {
      $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
    }
    $pdf .= 'trailer << /Size ' . ($maxId + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
  }

  /** @return string[] */
  private function wrap(string $text, int $width): array {
    $text = $this->ascii($text);
    $wrapped = wordwrap($text, $width, "\n", TRUE);
    return explode("\n", $wrapped);
  }

  private function escape(string $text): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($text));
  }

  private function ascii(string $text): string {
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return $converted !== FALSE ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
  }

}
