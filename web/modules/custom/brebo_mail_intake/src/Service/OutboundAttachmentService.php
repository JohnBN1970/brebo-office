<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\brebo_document_data\Service\DocumentStorageLocator;
use Drupal\brebo_mail_intake\Contract\OutboundAttachmentPersistenceInterface;
use Drupal\brebo_mail_intake\Contract\OutboundAttachmentReadRepositoryInterface;

/** Stores and resolves controlled attachments for outbound mail. */
final class OutboundAttachmentService {

  private const MAX_TOTAL_BYTES = 26214400;

  public function __construct(
    private readonly OutboundAttachmentReadRepositoryInterface $attachmentReads,
    private readonly OutboundAttachmentPersistenceInterface $persistence,
    private readonly DocumentRepository $documents,
    private readonly DocumentStorageLocator $storageLocator,
    private readonly SourceMailboxAttachmentReader $sourceMailboxReader,
  ) {}

  /** @return array<int|string,string> */
  public function documentOptions(): array {
    $rows = $this->attachmentReads->recentDocuments();

    $options = [];
    foreach ($rows as $row) {
      $label = trim((string) ($row['title'] ?? $row['original_filename'] ?? 'Document'));
      $revision = trim((string) ($row['revision_code'] ?? ''));
      $options[(int) $row['id']] = $label . ($revision !== '' ? ' · revisie ' . $revision : '');
    }
    return $options;
  }

  /** @param int[] $fileIds
   *  @param int[] $documentIds
   */
  public function attach(int $communicationId, array $fileIds, array $documentIds): void {
    $files = [];
    foreach (array_unique(array_map('intval', $fileIds)) as $fileId) {
      $upload = $this->persistence->promoteUploadedFile($communicationId, $fileId);
      if ($upload === NULL) {
        continue;
      }
      $files[] = ['target_id' => $upload['id'], 'description' => $upload['filename']];
      $documentId = $this->registerUploadedDocument($communicationId, $upload);
      $this->documents->upsertCommunicationRelation($documentId, $communicationId, 'created_with');
      $this->documents->upsertCommunicationRelation($documentId, $communicationId, 'sent_with');
    }
    $this->persistence->attachUploadedFiles($communicationId, $files);

    foreach (array_unique(array_map('intval', $documentIds)) as $documentId) {
      if ($documentId <= 0 || !isset($this->documentOptions()[$documentId])) {
        continue;
      }
      $this->documents->upsertCommunicationRelation($documentId, $communicationId, 'sent_with');
    }
  }

  /** @return array<int,array{filecontent:string,filename:string,filemime:string}> */
  public function resolve(int $communicationId): array {
    $attachments = [];
    $seenHashes = [];
    $totalBytes = 0;

    foreach ($this->persistence->uploadedFiles($communicationId) as $file) {
      $this->append($attachments, $seenHashes, $totalBytes, $file['filecontent'], $file['filename'], $file['filemime']);
    }

    $relations = $this->documents->documentsForCommunication($communicationId);
    foreach ($relations as $relation) {
      if (!in_array((string) ($relation['relation_role'] ?? ''), ['sent_with', 'created_with'], TRUE)) {
        continue;
      }
      $documentId = (int) ($relation['id'] ?? 0);
      foreach ($this->resolveDocumentIds([$documentId]) as $attachment) {
        $this->append($attachments, $seenHashes, $totalBytes, $attachment['filecontent'], $attachment['filename'], $attachment['filemime']);
      }
    }

    return $attachments;
  }

  /**
   * Resolves canonical BREBO documents without requiring a communication node.
   *
   * @param int[] $documentIds
   * @return array<int,array{filecontent:string,filename:string,filemime:string}>
   */
  public function resolveDocumentIds(array $documentIds): array {
    $attachments = [];
    $seenHashes = [];
    $totalBytes = 0;
    foreach (array_unique(array_filter(array_map('intval', $documentIds))) as $documentId) {
      $document = $this->attachmentReads->document($documentId);
      if ($document === NULL) {
        throw new \RuntimeException('Een gekozen BREBO-document is niet meer beschikbaar.');
      }

      $location = $this->storageLocator->locate($documentId);
      $content = '';
      $filename = trim((string) ($document['original_filename'] ?? $document['title'] ?? 'document')) ?: 'document';
      $mime = trim((string) ($document['mime_type'] ?? 'application/octet-stream')) ?: 'application/octet-stream';

      if (($location['access_mode'] ?? '') === 'local_private') {
        $content = $this->persistence->readLocalUri((string) ($location['local_uri'] ?? '')) ?? '';
      }
      elseif (($location['access_mode'] ?? '') === 'source_provider') {
        $sourceSystem = $this->attachmentReads->latestSourceSystem($documentId) ?? '';
        $result = $this->sourceMailboxReader->read($sourceSystem, (string) ($location['storage_key'] ?? ''));
        if (($result['state'] ?? '') === 'available') {
          $content = (string) ($result['content'] ?? '');
          $filename = trim((string) ($result['filename'] ?? $filename)) ?: $filename;
          $mime = trim((string) ($result['mime_type'] ?? $mime)) ?: $mime;
        }
      }

      $expectedHash = strtolower(trim((string) ($document['sha256'] ?? '')));
      if ($content === '' || $expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $content))) {
        throw new \RuntimeException('Een gekozen BREBO-document kon niet integer worden opgehaald.');
      }
      $this->append($attachments, $seenHashes, $totalBytes, $content, $filename, $mime);
    }
    return $attachments;
  }

  /** @param array{id:int,filename:string,mime_type:string,file_size:int,uri:string,content:string,owner_name:string} $upload */
  private function registerUploadedDocument(int $communicationId, array $upload): int {
    $sha256 = hash('sha256', $upload['content']);
    $document = $this->documents->upsertDocument([
      'title' => $upload['filename'],
      'document_type' => 'mail_attachment',
      'original_filename' => $upload['filename'],
      'mime_type' => $upload['mime_type'],
      'file_size' => $upload['file_size'],
      'sha256' => $sha256,
      'storage_provider' => 'drupal_private',
      'storage_key' => $upload['uri'],
      'lifecycle_status' => 'active',
    ]);
    $this->documents->addSource((int) $document['id'], [
      'source_system' => 'brebo_outbound_mail',
      'source_external_id' => 'communication:' . $communicationId . ':file:' . $upload['id'],
      'communication_nid' => $communicationId,
      'source_actor' => $upload['owner_name'],
      'source_timestamp' => gmdate(DATE_ATOM),
      'source_timestamp_authoritative' => TRUE,
      'original_filename' => $upload['filename'],
      'sha256' => $sha256,
      'extraction_method' => 'direct_upload',
      'artifact_role' => 'original',
      'confidence' => 1.0,
      'review_status' => 'confirmed',
    ]);
    return (int) $document['id'];
  }

  /** @param array<int,array{filecontent:string,filename:string,filemime:string}> $attachments
   *  @param array<string,bool> $seenHashes
   */
  private function append(array &$attachments, array &$seenHashes, int &$totalBytes, string $content, string $filename, string $mime): void {
    $hash = hash('sha256', $content);
    if (isset($seenHashes[$hash])) {
      return;
    }
    $totalBytes += strlen($content);
    if ($totalBytes > self::MAX_TOTAL_BYTES) {
      throw new \RuntimeException('De gezamenlijke bijlagen zijn groter dan 25 MB.');
    }
    $seenHashes[$hash] = TRUE;
    $attachments[] = [
      'filecontent' => $content,
      'filename' => $filename,
      'filemime' => $mime !== '' ? $mime : 'application/octet-stream',
    ];
  }

}
