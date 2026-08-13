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

  /**
   * Creates a logical document or reuses an exact binary match.
   *
   * SHA-256 is a strong binary identity signal only. Reusing a binary does not
   * merge provenance or business context; those are appended separately.
   *
   * @param array<string, mixed> $metadata
   *
   * @return array{state:string,id:int,sha256:string}
   */
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

  /**
   * Appends provenance, while suppressing an exact repeated source import.
   *
   * @param array<string, mixed> $source
   *
   * @return array{state:string,id:int}
   */
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

    $id = (int) $this->database->insert('brebo_document_source')
      ->fields([
        'document_id' => $documentId,
        'source_system' => $sourceSystem,
        'source_external_id' => $externalId !== '' ? $externalId : NULL,
        'communication_nid' => !empty($source['communication_nid']) ? (int) $source['communication_nid'] : NULL,
        'source_actor' => $this->nullable($source['source_actor'] ?? NULL),
        'source_timestamp' => $this->nullable($source['source_timestamp'] ?? NULL),
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

  /**
   * Adds or updates one business context relation without copying the binary.
   *
   * context_id may be 0 only for BREBO/internal context.
   *
   * @param array<string, mixed> $relation
   *
   * @return array{state:string,id:int}
   */
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

  /**
   * Stores extracted evidence. It can never enter as canonical truth.
   *
   * @param array<string, mixed> $evidence
   *
   * @return int evidence id
   */
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
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
