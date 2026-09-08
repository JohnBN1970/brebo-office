<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/** Extracts document text through the configured BREBO-managed runtime. */
final class ManagedDocumentTextEnricher implements IntakeEnricherInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly DocumentTextExtractionProviderRegistry $providerRegistry,
    private readonly PurchaseInvoiceTextEnricher $invoiceTextEnricher,
  ) {}

  public function supports(array $envelope): bool {
    foreach ((array) ($envelope['attachments'] ?? []) as $attachment) {
      if (!is_array($attachment) || (int) ($attachment['file_id'] ?? 0) <= 0) {
        continue;
      }
      $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? '')));
      if ($mimeType !== '' && $this->providerRegistry->providerFor($mimeType) !== NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function enrich(array $envelope): array {
    $originalAttachments = $envelope['attachments'] ?? [];
    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $evidence = is_array($payload['document_text_evidence'] ?? NULL) ? $payload['document_text_evidence'] : [];
    $seen = [];
    foreach ($evidence as $item) {
      if (!is_array($item)) {
        continue;
      }
      $hash = trim((string) ($item['content_sha256'] ?? ''));
      if ($hash !== '' && trim((string) ($item['text'] ?? '')) !== '') {
        $seen[$hash] = TRUE;
      }
    }

    $storage = $this->entityTypeManager->getStorage('file');
    $added = 0;
    $lastStatus = 'unavailable';

    foreach ((array) $originalAttachments as $attachment) {
      if (!is_array($attachment)) {
        continue;
      }
      $fid = (int) ($attachment['file_id'] ?? 0);
      if ($fid <= 0) {
        continue;
      }
      $mimeType = strtolower(trim((string) ($attachment['mime_type'] ?? '')));
      $provider = $mimeType !== '' ? $this->providerRegistry->providerFor($mimeType) : NULL;
      if ($provider === NULL) {
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
      $document = file_get_contents($realpath);
      if (!is_string($document) || $document === '') {
        continue;
      }

      $result = $provider->extract(
        $document,
        $mimeType,
        (string) ($attachment['filename'] ?? $file->getFilename()),
      );
      $lastStatus = trim((string) ($result['status'] ?? 'provider_error')) ?: 'provider_error';
      $text = trim((string) ($result['text'] ?? ''));
      if ($lastStatus !== 'extracted' || $text === '') {
        continue;
      }

      $evidence[] = [
        'filename' => (string) ($attachment['filename'] ?? $file->getFilename()),
        'mime_type' => $mimeType,
        'content_sha256' => $hash,
        'text' => $text,
        'extractor' => (string) ($result['extractor'] ?? 'brebo_managed_extraction_v1'),
        'confidence' => isset($result['confidence']) && is_numeric($result['confidence'])
          ? max(0.0, min(1.0, (float) $result['confidence']))
          : 0.8,
        'canonical_truth' => FALSE,
        'metadata' => is_array($result['metadata'] ?? NULL) ? $result['metadata'] : [],
      ];
      if ($hash !== '') {
        $seen[$hash] = TRUE;
      }
      $added++;
    }

    if ($added === 0) {
      $payload['managed_document_text_extraction_status'] = $lastStatus;
      $envelope['payload'] = $payload;
      return $envelope;
    }

    $payload['document_text_evidence'] = $evidence;
    $payload['document_text_extraction_status'] = 'extracted';
    $payload['managed_document_text_extraction_status'] = 'extracted';
    $envelope['payload'] = $payload;

    if (!in_array((string) ($envelope['classification'] ?? ''), ['purchase_invoice', 'supplier_invoice'], TRUE)) {
      return $envelope;
    }

    // Reuse the canonical text-to-invoice normalizer without ever replacing
    // the original attachment collection or source identity.
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

}
