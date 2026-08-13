<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

use Drupal\Core\Database\Connection;

/** Provider-neutral physical storage locator for immutable originals. */
final class DocumentStorageLocator {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function locate(int $documentId): array {
    $row = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'storage_provider', 'storage_key', 'lifecycle_status'])
      ->condition('id', $documentId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row || ($row['lifecycle_status'] ?? '') === 'deleted') {
      return $this->result($documentId, 'unknown', NULL, 'missing', 'none', NULL);
    }

    $provider = strtolower(trim((string) ($row['storage_provider'] ?? '')));
    $key = trim((string) ($row['storage_key'] ?? ''));
    $key = $key !== '' ? $key : NULL;

    if ($provider === 'drupal_private') {
      $uri = $key === NULL ? NULL : (str_starts_with($key, 'private://') ? $key : 'private://' . ltrim($key, '/'));
      return $this->result($documentId, $provider, $key, $uri !== NULL ? 'locatable' : 'unavailable', 'local_private', $uri);
    }

    if ($provider === 'source_mailbox') {
      return $this->result($documentId, $provider, $key, $key !== NULL ? 'source_available' : 'unavailable', 'source_provider', NULL);
    }

    if (in_array($provider, ['s3', 'r2', 'object_storage', 's3_compatible'], TRUE)) {
      return $this->result($documentId, $provider, $key, $key !== NULL ? 'provider_config_required' : 'unavailable', 'object_storage', NULL);
    }

    return $this->result($documentId, $provider !== '' ? $provider : 'unknown', $key, 'unsupported_provider', 'none', NULL);
  }

  /** @return array<string, mixed> */
  private function result(int $documentId, string $provider, ?string $key, string $availability, string $accessMode, ?string $localUri): array {
    return [
      'document_id' => $documentId,
      'provider' => $provider,
      'storage_key' => $key,
      'availability' => $availability,
      'access_mode' => $accessMode,
      'immutable_original' => TRUE,
      'local_uri' => $localUri,
    ];
  }

}
