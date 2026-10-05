<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Infrastructure;

use Drupal\brebo_document_data\Contract\DocumentRevisionReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for context-scoped document revision reads. */
final class DatabaseDocumentRevisionReadRepository implements DocumentRevisionReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function revisions(string $contextType, int $contextId, string $documentFamily): array {
    $query = $this->database->select('brebo_document', 'd');
    $query->innerJoin('brebo_document_context', 'c', 'c.document_id = d.id');
    $query->leftJoin('brebo_document_source', 's', 's.document_id = d.id AND s.source_timestamp_authoritative = 1');
    $query->fields('d');
    $query->addExpression('MAX(s.source_timestamp_unix)', 'authoritative_source_timestamp');
    $query->condition('c.context_type', $contextType);
    $query->condition('c.context_id', $contextId);
    $query->condition('d.document_family', $documentFamily);
    $query->condition('d.lifecycle_status', 'deleted', '<>');
    $query->groupBy('d.id');
    foreach (['title', 'document_type', 'document_family', 'revision_code', 'original_filename', 'mime_type', 'file_size', 'sha256', 'storage_provider', 'storage_key', 'lifecycle_status', 'created', 'changed'] as $field) {
      $query->groupBy('d.' . $field);
    }
    $query->orderBy('authoritative_source_timestamp', 'DESC');
    $query->orderBy('d.id', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

}
