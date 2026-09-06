<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Extracts OCR text from canonical scanned invoice documents. */
final class LocalOcrTextEnricher implements IntakeEnricherInterface {

  private const IMAGE_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/tiff',
  ];

  private const MAX_PDF_PAGES = 10;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly PurchaseInvoiceTextEnricher $invoiceTextEnricher,
  ) {}

  public function supports(array $envelope): bool {
    if (!in_array((string) ($envelope['classification'] ?? ''), ['purchase_invoice', 'supplier_invoice'], TRUE)) {
      return FALSE;
    }

    foreach ((array) ($envelope['attachments'] ?? []) as $attachment) {
      if (!is_array($attachment) || (int) ($attachment['file_id'] ?? 0) <= 0) {
        continue;
      }
      $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
      if ($mime === 'application/pdf' || in_array($mime, self::IMAGE_MIME_TYPES, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function enrich(array $envelope): array {
    $originalAttachments = $envelope['attachments'] ?? [];
    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $evidence = is_array($payload['document_text_evidence'] ?? NULL) ? $payload['document_text_evidence'] : [];

    $tesseract = (new ExecutableFinder())->find('tesseract');
    if ($tesseract === NULL) {
      $payload['document_ocr_status'] = 'tesseract_unavailable';
      $envelope['payload'] = $payload;
      return $envelope;
    }

    $pdftoppm = (new ExecutableFinder())->find('pdftoppm');
    $language = $this->ocrLanguage($tesseract);
    $storage = $this->entityTypeManager->getStorage('file');
    $added = 0;

    foreach ((array) $originalAttachments as $attachment) {
      if (!is_array($attachment)) {
        continue;
      }
      $fid = (int) ($attachment['file_id'] ?? 0);
      if ($fid <= 0) {
        continue;
      }
      $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
      if ($mime !== 'application/pdf' && !in_array($mime, self::IMAGE_MIME_TYPES, TRUE)) {
        continue;
      }

      $hash = trim((string) ($attachment['content_sha256'] ?? $attachment['sha256'] ?? ''));
      if ($this->hasTextEvidence($evidence, $hash)) {
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

      $pageTexts = $mime === 'application/pdf'
        ? $this->ocrPdf($realpath, $tesseract, $pdftoppm, $language)
        : $this->ocrImage($realpath, $tesseract, $language);
      if ($pageTexts === []) {
        continue;
      }

      $text = trim(implode("\n\n", array_values($pageTexts)));
      if ($text === '') {
        continue;
      }

      $evidence[] = [
        'filename' => (string) ($attachment['filename'] ?? $file->getFilename()),
        'mime_type' => $mime,
        'content_sha256' => $hash,
        'text' => $text,
        'pages' => array_map(
          static fn(int|string $page, string $pageText): array => [
            'page' => is_int($page) ? $page : NULL,
            'text' => $pageText,
          ],
          array_keys($pageTexts),
          array_values($pageTexts),
        ),
        'extractor' => 'local_tesseract_v1',
        'language' => $language,
        'confidence' => 0.80,
        'canonical_truth' => FALSE,
      ];
      $added++;
    }

    if ($added === 0) {
      if (!isset($payload['document_ocr_status'])) {
        $payload['document_ocr_status'] = $pdftoppm === NULL ? 'no_ocr_text_or_pdf_renderer_unavailable' : 'no_ocr_text';
      }
      $envelope['payload'] = $payload;
      return $envelope;
    }

    $payload['document_text_evidence'] = $evidence;
    $payload['document_ocr_status'] = 'extracted';
    $envelope['payload'] = $payload;

    // Reuse the canonical text->invoice normalizer while preserving the exact
    // original attachment set as evidence.
    $working = $envelope;
    $working['attachments'] = array_values(array_merge((array) $originalAttachments, [[
      'type' => 'mail_attachment_evidence',
      'evidence' => [
        'context_text' => implode("\n\n", array_map(
          static fn(array $item): string => trim((string) ($item['text'] ?? '')),
          array_values(array_filter($evidence, 'is_array')),
        )),
      ],
    ]]));
    $working = $this->invoiceTextEnricher->enrich($working);
    $working['attachments'] = $originalAttachments;
    return $working;
  }

  /** @return array<int|string,string> */
  private function ocrImage(string $path, string $tesseract, string $language): array {
    $process = new Process([$tesseract, $path, 'stdout', '-l', $language, '--psm', '6']);
    $process->setTimeout(30.0);
    $process->run();
    if (!$process->isSuccessful()) {
      return [];
    }
    $text = $this->normalize($process->getOutput());
    return $text === '' ? [] : ['image' => $text];
  }

  /** @return array<int,string> */
  private function ocrPdf(string $path, string $tesseract, ?string $pdftoppm, string $language): array {
    if ($pdftoppm === NULL) {
      return [];
    }

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'brebo-ocr-' . bin2hex(random_bytes(8));
    if (!@mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
      return [];
    }

    try {
      $prefix = $directory . DIRECTORY_SEPARATOR . 'page';
      $render = new Process([
        $pdftoppm,
        '-png',
        '-r', '220',
        '-f', '1',
        '-l', (string) self::MAX_PDF_PAGES,
        $path,
        $prefix,
      ]);
      $render->setTimeout(45.0);
      $render->run();
      if (!$render->isSuccessful()) {
        return [];
      }

      $images = glob($prefix . '-*.png') ?: [];
      natsort($images);
      $pages = [];
      foreach (array_values($images) as $index => $image) {
        $text = $this->ocrImage($image, $tesseract, $language);
        if ($text !== []) {
          $pages[$index + 1] = (string) reset($text);
        }
      }
      return $pages;
    }
    finally {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $temporary) {
        @unlink($temporary);
      }
      @rmdir($directory);
    }
  }

  private function ocrLanguage(string $tesseract): string {
    $process = new Process([$tesseract, '--list-langs']);
    $process->setTimeout(5.0);
    $process->run();
    if (!$process->isSuccessful()) {
      return 'eng';
    }

    $languages = array_values(array_filter(array_map('trim', preg_split('/\R/u', $process->getOutput()) ?: [])));
    $languages = array_values(array_filter($languages, static fn(string $value): bool => !str_starts_with(strtolower($value), 'list of available')));
    if (in_array('nld', $languages, TRUE) && in_array('eng', $languages, TRUE)) {
      return 'nld+eng';
    }
    if (in_array('nld', $languages, TRUE)) {
      return 'nld';
    }
    if (in_array('eng', $languages, TRUE)) {
      return 'eng';
    }
    return $languages[0] ?? 'eng';
  }

  private function hasTextEvidence(array $evidence, string $hash): bool {
    if ($hash === '') {
      return FALSE;
    }
    foreach ($evidence as $item) {
      if (is_array($item) && (string) ($item['content_sha256'] ?? '') === $hash && trim((string) ($item['text'] ?? '')) !== '') {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function normalize(string $text): string {
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[ \t]+$/mu', '', $text) ?? $text;
    $text = preg_replace('/\R{4,}/u', "\n\n\n", $text) ?? $text;
    return trim($text);
  }

}
