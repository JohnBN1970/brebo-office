<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves document storage without exposing business-context folder semantics.
 */
final class DocumentStorageLocator {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /** @return array<string, mixed>|null */
  public function locate(int $documentId): ?array {
    if ($documentId <= 0) {
      return NULL;
    }

    $row = $this->database->select('brebo_document', 'd')
      ->fields('d', [
        'id',
        'title',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
        'storage_provider',
        'storage_key',
        'lifecycle_status',
      ])
      ->condition('id', $documentId)
      ->condition('lifecycle_status', 'deleted', '<>')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $provider = trim((string) ($row['storage_provider'] ?? '')) ?: 'unknown';
    $key = isset($row['storage_key']) ? trim((string) $row['storage_key']) : '';

    return [
      'document_id' => (int) $row['id'],
      'filename' => trim((string) ($row['original_filename'] ?? '')) ?: (string) $row['title'],
      'mime_type' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
      'file_size' => max(0, (int) ($row['file_size'] ?? 0)),
      'sha256' => strtolower((string) $row['sha256']),
      'provider' => $provider,
      'key' => $key !== '' ? $key : NULL,
      'availability' => $this->availability($provider, $key),
      'immutable_original' => TRUE,
    ];
  }

  private function availability(string $provider, string $key): string {
    if ($key === '') {
      return 'metadata_only';
    }

    return match ($provider) {
      'drupal_private', 'local_private' => 'stored',
      'source_mailbox' => 'source_retrievable',
      's3', 'r2', 's3_compatible' => 'object_storage',
      default => 'external',
    };
  }

}
