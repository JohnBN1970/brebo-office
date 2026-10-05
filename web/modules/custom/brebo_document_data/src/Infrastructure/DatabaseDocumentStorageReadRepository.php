<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Infrastructure;

use Drupal\brebo_document_data\Contract\DocumentStorageReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for immutable document storage metadata. */
final class DatabaseDocumentStorageReadRepository implements DocumentStorageReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function storageRow(int $documentId): ?array {
    $row = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'storage_provider', 'storage_key', 'lifecycle_status'])
      ->condition('id', $documentId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    return [
      'id' => (int) $row['id'],
      'storage_provider' => (string) ($row['storage_provider'] ?? ''),
      'storage_key' => trim((string) ($row['storage_key'] ?? '')) !== '' ? (string) $row['storage_key'] : NULL,
      'lifecycle_status' => (string) ($row['lifecycle_status'] ?? ''),
    ];
  }

}
