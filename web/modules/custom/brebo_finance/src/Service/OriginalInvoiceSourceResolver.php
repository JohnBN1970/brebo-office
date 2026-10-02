<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\OriginalInvoiceSourceRepositoryInterface;
use Drupal\file\FileInterface;

/** Resolves the canonical source document behind a Finance purchase invoice. */
final class OriginalInvoiceSourceResolver {

  public function __construct(private readonly OriginalInvoiceSourceRepositoryInterface $repository) {}

  /**
   * @param callable(int): ?FileInterface $fileLoader
   *
   * @return array{file: FileInterface, filename: string, mime_type: string}|null
   */
  public function resolve(int $invoiceId, callable $fileLoader): ?array {
    if ($invoiceId <= 0 || !$this->repository->available()) {
      return NULL;
    }

    $rows = $this->repository->payloads($invoiceId);

    foreach ($rows as $encoded) {
      $payload = json_decode((string) $encoded, TRUE);
      if (!is_array($payload)) {
        continue;
      }
      foreach ((array) ($payload['attachments'] ?? []) as $attachment) {
        if (!is_array($attachment) || (int) ($attachment['file_id'] ?? 0) <= 0) {
          continue;
        }
        $file = $fileLoader((int) $attachment['file_id']);
        if (!$file instanceof FileInterface || !$file->isPermanent()) {
          continue;
        }
        $uri = $file->getFileUri();
        if (!str_starts_with($uri, 'private://brebo-intake/')) {
          continue;
        }
        $expectedHash = trim((string) ($attachment['content_sha256'] ?? ''));
        if ($expectedHash !== '' && !hash_equals($expectedHash, trim((string) ($payload['source_record_id'] ?? $expectedHash)))) {
          // The attachment itself remains authoritative; this guard only
          // rejects explicit conflicting provenance when both values exist.
          $sourceRecord = trim((string) ($payload['source_record_id'] ?? ''));
          if ($sourceRecord !== '' && preg_match('/^[a-f0-9]{64}$/i', $sourceRecord) === 1) {
            continue;
          }
        }
        return [
          'file' => $file,
          'filename' => (string) ($attachment['filename'] ?? $file->getFilename()),
          'mime_type' => (string) ($attachment['mime_type'] ?? $file->getMimeType()),
        ];
      }
    }

    return NULL;
  }

}
