<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Extracts embedded text from canonical intake PDFs without changing evidence. */
final class LocalPdfTextEnricher implements IntakeEnricherInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public function supports(array $envelope): bool {
    if (!in_array((string) ($envelope['classification'] ?? ''), ['purchase_invoice', 'supplier_invoice'], TRUE)) {
      return FALSE;
    }
    foreach ((array) ($envelope['attachments'] ?? []) as $attachment) {
      if (is_array($attachment) && (int) ($attachment['file_id'] ?? 0) > 0 && strtolower((string) ($attachment['mime_type'] ?? '')) === 'application/pdf') {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function enrich(array $envelope): array {
    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $evidence = is_array($payload['document_text_evidence'] ?? NULL) ? $payload['document_text_evidence'] : [];
    $seen = [];
    foreach ($evidence as $item) {
      if (is_array($item)) {
        $seen[(string) ($item['content_sha256'] ?? '')] = TRUE;
      }
    }

    $binary = (new ExecutableFinder())->find('pdftotext');
    if ($binary === NULL) {
      $payload['document_text_extraction_status'] = 'pdftotext_unavailable';
      $envelope['payload'] = $payload;
      return $envelope;
    }

    $storage = $this->entityTypeManager->getStorage('file');
    foreach ((array) ($envelope['attachments'] ?? []) as $attachment) {
      if (!is_array($attachment) || strtolower((string) ($attachment['mime_type'] ?? '')) !== 'application/pdf') {
        continue;
      }
      $fid = (int) ($attachment['file_id'] ?? 0);
      if ($fid <= 0) {
        continue;
      }
      $hash = trim((string) ($attachment['content_sha256'] ?? $attachment['sha256'] ?? ''));
      if ($hash !== '' && isset($seen[$hash])) {
        continue;
      }
      $file = $storage->load($fid);
      if (!$file instanceof FileInterface || !$file->isPermanent()) {
        continue;
      }
      $uri = $file->getFileUri();
      if (!str_starts_with($uri, 'private://brebo-intake/')) {
        continue;
      }
      $realpath = $this->fileSystem->realpath($uri);
      if (!is_string($realpath) || $realpath === '' || !is_file($realpath) || !is_readable($realpath)) {
        continue;
      }

      $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $realpath, '-']);
      $process->setTimeout(20.0);
      $process->run();
      if (!$process->isSuccessful()) {
        continue;
      }
      $text = $this->normalize($process->getOutput());
      if ($text === '') {
        continue;
      }
      $evidence[] = [
        'filename' => (string) ($attachment['filename'] ?? $file->getFilename()),
        'mime_type' => 'application/pdf',
        'content_sha256' => $hash,
        'text' => $text,
        'extractor' => 'local_pdftotext_v1',
        'confidence' => 0.98,
        'canonical_truth' => FALSE,
      ];
      if ($hash !== '') {
        $seen[$hash] = TRUE;
      }
    }

    if ($evidence !== []) {
      $payload['document_text_evidence'] = $evidence;
      $payload['document_text_extraction_status'] = 'extracted';
    }
    elseif (!isset($payload['document_text_extraction_status'])) {
      $payload['document_text_extraction_status'] = 'no_embedded_pdf_text';
    }
    $envelope['payload'] = $payload;
    return $envelope;
  }

  private function normalize(string $text): string {
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[ \t]+$/mu', '', $text) ?? $text;
    $text = preg_replace('/\R{4,}/u', "\n\n\n", $text) ?? $text;
    return trim($text);
  }

}
