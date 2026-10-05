<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\OutboundAttachmentReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseOutboundAttachmentReadRepository implements OutboundAttachmentReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function recentDocuments(int $limit = 100): array {
    if (!$this->database->schema()->tableExists('brebo_document')) {
      return [];
    }
    return array_values($this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'title', 'original_filename', 'revision_code'])
      ->condition('lifecycle_status', 'deleted', '<>')
      ->orderBy('changed', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: []);
  }

  public function document(int $documentId): ?array {
    if (!$this->database->schema()->tableExists('brebo_document')) {
      return NULL;
    }
    $row = $this->database->select('brebo_document', 'd')
      ->fields('d', ['title', 'original_filename', 'mime_type', 'sha256'])
      ->condition('id', $documentId)
      ->condition('lifecycle_status', 'deleted', '<>')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function latestSourceSystem(int $documentId): ?string {
    if (!$this->database->schema()->tableExists('brebo_document_source')) {
      return NULL;
    }
    $value = $this->database->select('brebo_document_source', 's')
      ->fields('s', ['source_system'])
      ->condition('document_id', $documentId)
      ->orderBy('source_timestamp_authoritative', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return $value === FALSE ? NULL : (string) $value;
  }

}
