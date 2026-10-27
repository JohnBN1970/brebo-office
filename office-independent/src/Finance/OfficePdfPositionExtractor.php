<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Optional local Poppler positional extractor. Never sends a private invoice
 * to a third party. Requires pdftotext on the server and DOM extension.
 * No OCR: scanned PDFs return an empty array.
 */
final class OfficePdfPositionExtractor {
  public static function available(): bool {
    return function_exists('proc_open') && class_exists(\DOMDocument::class);
  }

  public static function extract(string $path): array {
    if (!self::available() || !is_file($path) || is_link($path)
      || filesize($path) < 1 || filesize($path) > 10 * 1024 * 1024
      || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') return [];
    $pipes = [];
    $process = @proc_open(
      ['pdftotext', '-bbox', '-enc', 'UTF-8', $path, '-'],
      [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']],
      $pipes, null, null, ['bypass_shell' => true]
    );
    if (!is_resource($process)) return [];
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 10);
    $xml = stream_get_contents($pipes[1], 2 * 1024 * 1024 + 1);
    fclose($pipes[1]);
    stream_get_contents($pipes[2], 4096);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || !is_string($xml) || strlen($xml) > 2 * 1024 * 1024) return [];
    return self::fromBboxXml($xml);
  }

  public static function fromBboxXml(string $xml): array {
    if (strlen($xml) > 2 * 1024 * 1024 || !class_exists(\DOMDocument::class)) return [];
    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
      if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return [];
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    $xpath = new \DOMXPath($dom);
    $blocks = [];
    $pageNo = 0;
    foreach ($xpath->query('//*[local-name()="page"]') as $page) {
      ++$pageNo;
      if ($pageNo > 100) return [];
      $width = (float)$page->getAttribute('width');
      $height = (float)$page->getAttribute('height');
      if ($width <= 0 || $height <= 0) return [];
      foreach ($xpath->query('.//*[local-name()="word"]', $page) as $word) {
        $text = trim($word->textContent);
        if ($text === '') continue;
        $x = (float)$word->getAttribute('xMin') / $width;
        $y = (float)$word->getAttribute('yMin') / $height;
        if (strlen($text) > 500 || !is_finite($x) || !is_finite($y)
          || $x < 0 || $x > 1 || $y < 0 || $y > 1) return [];
        $blocks[] = ['page'=>$pageNo, 'text'=>$text, 'x'=>$x, 'y'=>$y];
        if (count($blocks) > 2000) return [];
      }
    }
    return OfficeInvoiceLayout::sanitize($blocks);
  }
}
