<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Extracts embedded text from digital supplier quote PDFs while preserving layout. */
final class SupplierQuotePdfTextExtractor {

  /**
   * @return array{status:string,text:string,extractor:string,confidence:float}
   */
  public function extract(string $path): array {
    if ($path === '' || !is_file($path) || !is_readable($path)) {
      return $this->empty('file_unavailable');
    }

    $binary = (new ExecutableFinder())->find('pdftotext');
    if ($binary === NULL) {
      return $this->empty('pdftotext_unavailable');
    }

    $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);
    $process->setTimeout(20.0);
    $process->run();
    if (!$process->isSuccessful()) {
      return $this->empty('pdftotext_failed');
    }

    $text = $this->normalize($process->getOutput());
    if ($text === '') {
      return $this->empty('no_embedded_pdf_text');
    }

    return [
      'status' => 'extracted',
      'text' => $text,
      'extractor' => 'local_pdftotext_layout_v1',
      'confidence' => 0.99,
    ];
  }

  /** @return array{status:string,text:string,extractor:string,confidence:float} */
  private function empty(string $status): array {
    return [
      'status' => $status,
      'text' => '',
      'extractor' => 'local_pdftotext_layout_v1',
      'confidence' => 0.0,
    ];
  }

  private function normalize(string $text): string {
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[ \t]+$/mu', '', $text) ?? $text;
    $text = preg_replace('/\R{4,}/u', "\n\n\n", $text) ?? $text;
    return trim($text);
  }

}
