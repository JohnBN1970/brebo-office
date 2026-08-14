<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Duplicate-safe storage for BREBO documents, provenance, contexts and evidence.
 */
final class DocumentRepository {

  private const CONTEXT_TYPES = ['building', 'project', 'organization', 'contact', 'brebo'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /** @param array<string, mixed> $metadata */
  public function upsertDocument(array $metadata): array {
    $sha256 = strtolower(trim((string) ($metadata['sha256'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
      throw new \InvalidArgumentException('Een geldig SHA-256 documenthash is verplicht.');
    }

    $existingId = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id'])
      ->condition('sha256', $sha256)
      ->condition('lifecycle_status', 'deleted', '<>')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($existingId) {
      return ['state' => 'reused', 'id' => (int) $existingId, 'sha256' => $sha256];
    }

    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('brebo_document')
      ->fields([
        'title' => $this->clean($metadata['title'] ?? $metadata['original_filename'] ?? 'Document') ?: 'Document',
        'document_type' => $this->nullable($metadata['document_type'] ?? NULL),
        'document_family' => $this->nullable($metadata['document_family'] ?? NULL),
        'revision_code' => $this->nullable($metadata['revision_code'] ?? NULL),
        'original_filename' => $this->nullable($metadata['original_filename'] ?? NULL),
        'mime_type' => $this->nullable($metadata['mime_type'] ?? NULL),
        'file_size' => max(0, (int) ($metadata['file_size'] ?? 0)),
        'sha256' => $sha256,
        'storage_provider' => $this->clean($metadata['storage_provider'] ?? 'drupal_private') ?: 'drupal_private',
        'storage_key' => $this->nullable($metadata['storage_key'] ?? NULL),
        'lifecycle_status' => $this->clean($metadata['lifecycle_status'] ?? 'active') ?: 'active',
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    return ['state' => 'created', 'id' => $id, 'sha256' => $sha256];
  }

  /** @param array<string, mixed> $source */
  public function addSource(int $documentId, array $source): array {
    $this->assertDocument($documentId);
    $sourceSystem = $this->clean($source['source_system'] ?? '');
    $sha256 = strtolower($this->clean($source['sha256'] ?? ''));
    if ($sourceSystem === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
      throw new \InvalidArgumentException('DocumentSource vereist source_system en geldige SHA-256.');
    }

    $externalId = $this->clean($source['source_external_id'] ?? '');
    if ($externalId !== '') {
      $existingId = $this->database->select('brebo_document_source', 's')
        ->fields('s', ['id'])
        ->condition('document_id', $documentId)
        ->condition('source_system', $sourceSystem)
        ->condition('source_external_id', $externalId)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($existingId) {
        return ['state' => 'reused', 'id' => (int) $existingId];
      }
    }

    $sourceTimestamp = $this->nullable($source['source_timestamp'] ?? NULL);
    $sourceTimestampUnix = NULL;
    if ($sourceTimestamp !== NULL) {
      $parsed = strtotime($sourceTimestamp);
      if ($parsed !== FALSE && $parsed >= 0) {
        $sourceTimestampUnix = $parsed;
      }
    }
    $authoritative = (int) (($source['source_timestamp_authoritative'] ?? $sourceTimestampUnix !== NULL) ? 1 : 0);

    $id = (int) $this->database->insert('brebo_document_source')
      ->fields([
        'document_id' => $documentId,
        'source_system' => $sourceSystem,
        'source_external_id' => $externalId !== '' ? $externalId : NULL,
        'communication_nid' => !empty($source['communication_nid']) ? (int) $source['communication_nid'] : NULL,
        'source_actor' => $this->nullable($source['source_actor'] ?? NULL),
        'source_timestamp' => $sourceTimestamp,
        'source_timestamp_unix' => $sourceTimestampUnix,
        'source_timestamp_authoritative' => $authoritative,
        'original_filename' => $this->nullable($source['original_filename'] ?? NULL),
        'sha256' => $sha256,
        'extraction_method' => $this->nullable($source['extraction_method'] ?? NULL),
        'fragment_location' => $this->nullable($source['fragment_location'] ?? NULL),
        'artifact_role' => $this->clean($source['artifact_role'] ?? 'original') ?: 'original',
        'confidence' => $this->confidence($source['confidence'] ?? 1.0),
        'review_status' => $this->clean($source['review_status'] ?? 'unreviewed') ?: 'unreviewed',
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();

    return ['state' => 'created', 'id' => $id];
  }

  /** @param array<string, mixed> $relation */
  public function upsertContext(int $documentId, array $relation): array {
    $this->assertDocument($documentId);
    $type = strtolower($this->clean($relation['context_type'] ?? ''));
    if (!in_array($type, self::CONTEXT_TYPES, TRUE)) {
      throw new \InvalidArgumentException('Onbekend document context_type.');
    }
    $contextId = max(0, (int) ($relation['context_id'] ?? 0));
    if ($type !== 'brebo' && $contextId === 0) {
      throw new \InvalidArgumentException('Niet-BREBO documentcontext vereist context_id.');
    }
    $role = strtolower($this->clean($relation['relation_role'] ?? 'supporting')) ?: 'supporting';

    $existingId = $this->database->select('brebo_document_context', 'c')
      ->fields('c', ['id'])
      ->condition('document_id', $documentId)
      ->condition('context_type', $type)
      ->condition('context_id', $contextId)
      ->condition('relation_role', $role)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $now = $this->time->getRequestTime();
    $fields = [
      'confidence' => $this->confidence($relation['confidence'] ?? 0.0),
      'relation_source' => $this->nullable($relation['relation_source'] ?? NULL),
      'review_status' => $this->clean($relation['review_status'] ?? 'proposed') ?: 'proposed',
      'confirmed_by_uid' => !empty($relation['confirmed_by_uid']) ? (int) $relation['confirmed_by_uid'] : NULL,
      'confirmed_at' => !empty($relation['confirmed_at']) ? (int) $relation['confirmed_at'] : NULL,
      'changed' => $now,
    ];

    if ($existingId) {
      $this->database->update('brebo_document_context')->fields($fields)->condition('id', (int) $existingId)->execute();
      return ['state' => 'updated', 'id' => (int) $existingId];
    }

    $id = (int) $this->database->insert('brebo_document_context')
      ->fields([
        'document_id' => $documentId,
        'context_type' => $type,
        'context_id' => $contextId,
        'relation_role' => $role,
        'created' => $now,
      ] + $fields)
      ->execute();
    return ['state' => 'created', 'id' => $id];
  }

  /** @param array<string, mixed> $evidence */
  public function addEvidence(int $documentId, array $evidence): int {
    $this->assertDocument($documentId);
    $type = $this->clean($evidence['evidence_type'] ?? '');
    $value = trim((string) ($evidence['extracted_value'] ?? ''));
    if ($type === '' || $value === '') {
      throw new \InvalidArgumentException('DocumentEvidence vereist type en extracted_value.');
    }

    return (int) $this->database->insert('brebo_document_evidence')
      ->fields([
        'document_id' => $documentId,
        'source_id' => !empty($evidence['source_id']) ? (int) $evidence['source_id'] : NULL,
        'fragment_location' => $this->nullable($evidence['fragment_location'] ?? NULL),
        'evidence_type' => $type,
        'extracted_value' => $value,
        'normalized_value' => $this->nullable($evidence['normalized_value'] ?? NULL),
        'extraction_method' => $this->nullable($evidence['extraction_method'] ?? NULL),
        'confidence' => $this->confidence($evidence['confidence'] ?? 0.0),
        'validation_source' => $this->nullable($evidence['validation_source'] ?? NULL),
        'validation_status' => $this->clean($evidence['validation_status'] ?? 'unvalidated') ?: 'unvalidated',
        'canonical_truth' => 0,
        'reviewed_by_uid' => NULL,
        'reviewed_at' => NULL,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function revisionsForFamily(string $documentFamily): array {
    $family = $this->clean($documentFamily);
    if ($family === '') {
      return [];
    }

    $query = $this->database->select('brebo_document', 'd');
    $query->leftJoin('brebo_document_source', 's', 's.document_id = d.id AND s.source_timestamp_authoritative = 1');
    $query->fields('d');
    $query->addExpression('MAX(s.source_timestamp_unix)', 'authoritative_source_timestamp');
    $query->condition('d.document_family', $family);
    $query->condition('d.lifecycle_status', 'deleted', '<>');
    $query->groupBy('d.id');
    foreach (['title', 'document_type', 'document_family', 'revision_code', 'original_filename', 'mime_type', 'file_size', 'sha256', 'storage_provider', 'storage_key', 'lifecycle_status', 'created', 'changed'] as $field) {
      $query->groupBy('d.' . $field);
    }
    $query->orderBy('authoritative_source_timestamp', 'DESC');
    $query->orderBy('d.id', 'DESC');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array<int, array<string, mixed>> */
  public function contextsForDocument(int $documentId): array {
    return $this->database->select('brebo_document_context', 'c')
      ->fields('c')
      ->condition('document_id', $documentId)
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array<int, array<string, mixed>> */
  public function sourcesForDocument(int $documentId): array {
    return $this->database->select('brebo_document_source', 's')
      ->fields('s')
      ->condition('document_id', $documentId)
      ->orderBy('source_timestamp_authoritative', 'DESC')
      ->orderBy('source_timestamp_unix', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }


  public function upsertCommunicationRelation(int $documentId, int $communicationNid, string $role): array {
    $this->assertDocument($documentId);
    $role = strtolower($this->clean($role));
    if ($communicationNid <= 0 || !in_array($role, ['received_with', 'sent_with', 'created_with'], TRUE)) {
      throw new \InvalidArgumentException('Document-communicatierelatie vereist communicatie en geldige rol.');
    }
    $this->ensureCommunicationTable();
    $existing = $this->database->select('brebo_document_communication', 'r')
      ->fields('r', ['id'])
      ->condition('document_id', $documentId)
      ->condition('communication_nid', $communicationNid)
      ->condition('relation_role', $role)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing) {
      return ['state' => 'reused', 'id' => (int) $existing];
    }
    $id = (int) $this->database->insert('brebo_document_communication')
      ->fields([
        'document_id' => $documentId,
        'communication_nid' => $communicationNid,
        'relation_role' => $role,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
    return ['state' => 'created', 'id' => $id];
  }

  /** @return array<int,array<string,mixed>> */
  public function communicationsForDocument(int $documentId): array {
    $this->ensureCommunicationTable();
    $query = $this->database->select('brebo_document_communication', 'r');
    $query->leftJoin('node_field_data', 'n', 'n.nid = r.communication_nid AND n.default_langcode = 1');
    $query->fields('r');
    $query->addField('n', 'title', 'communication_title');
    $query->condition('r.document_id', $documentId)->orderBy('r.created', 'DESC');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /** @return array<int,array<string,mixed>> */
  public function documentsForCommunication(int $communicationNid): array {
    $this->ensureCommunicationTable();
    $query = $this->database->select('brebo_document_communication', 'r');
    $query->innerJoin('brebo_document', 'd', 'd.id = r.document_id');
    $query->fields('r', ['relation_role']);
    $query->fields('d', ['id', 'title', 'original_filename', 'document_family', 'revision_code', 'lifecycle_status']);
    $query->condition('r.communication_nid', $communicationNid)
      ->condition('d.lifecycle_status', 'deleted', '<>')
      ->orderBy('r.created', 'DESC');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  private function ensureCommunicationTable(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_document_communication')) {
      return;
    }
    $schema->createTable('brebo_document_communication', [
      'description' => 'Explicit many-to-many relations between canonical documents and communication events.',
      'fields' => [
        'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
        'document_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'communication_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'relation_role' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['document_communication_role' => ['document_id', 'communication_nid', 'relation_role']],
      'indexes' => [
        'document_id' => ['document_id'],
        'communication_nid' => ['communication_nid'],
        'communication_role' => ['communication_nid', 'relation_role'],
      ],
    ]);
  }

  private function assertDocument(int $documentId): void {
    $exists = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id'])
      ->condition('id', $documentId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!$exists) {
      throw new \InvalidArgumentException(sprintf('Onbekend BREBO document %d.', $documentId));
    }
  }

  private function clean(mixed $value): string {
    return trim((string) ($value ?? ''));
  }

  private function nullable(mixed $value): ?string {
    $value = $this->clean($value);
    return $value === '' ? NULL : $value;
  }

  private function confidence(mixed $value): float {
    return max(0.0, min(1.0, (float) $value));
  }

}
