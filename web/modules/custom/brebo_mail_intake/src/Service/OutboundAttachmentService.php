<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\brebo_document_data\Service\DocumentStorageLocator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\NodeInterface;

/** Stores and resolves controlled attachments for outbound mail. */
final class OutboundAttachmentService {

  private const MAX_TOTAL_BYTES = 26214400;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
    private readonly DocumentRepository $documents,
    private readonly DocumentStorageLocator $storageLocator,
    private readonly SourceMailboxAttachmentReader $sourceMailboxReader,
  ) {}

  /** @return array<int|string,string> */
  public function documentOptions(): array {
    if (!$this->database->schema()->tableExists('brebo_document')) {
      return [];
    }
    $rows = $this->database->select('brebo_document', 'd')
      ->fields('d', ['id', 'title', 'original_filename', 'revision_code'])
      ->condition('lifecycle_status', 'deleted', '<>')
      ->orderBy('changed', 'DESC')
      ->range(0, 100)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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
  public function attach(NodeInterface $draft, array $fileIds, array $documentIds): void {
    $files = [];
    foreach (array_unique(array_map('intval', $fileIds)) as $fileId) {
      $file = $this->entityTypeManager->getStorage('file')->load($fileId);
      if (!$file instanceof FileInterface) {
        continue;
      }
      $file->setPermanent();
      $file->save();
      $files[] = ['target_id' => (int) $file->id(), 'description' => $file->getFilename()];
      $this->fileUsage->add($file, 'brebo_mail_intake', 'node', (string) $draft->id());
      $documentId = $this->registerUploadedDocument($draft, $file);
      $this->documents->upsertCommunicationRelation($documentId, (int) $draft->id(), 'created_with');
      $this->documents->upsertCommunicationRelation($documentId, (int) $draft->id(), 'sent_with');
    }
    if ($draft->hasField('field_brebo_comm_attachments') && $files !== []) {
      $draft->set('field_brebo_comm_attachments', $files);
      $draft->setNewRevision(TRUE);
      $draft->setRevisionLogMessage('Uitgaande privébijlagen aan mailconcept gekoppeld.');
      $draft->save();
    }

    foreach (array_unique(array_map('intval', $documentIds)) as $documentId) {
      if ($documentId <= 0 || !isset($this->documentOptions()[$documentId])) {
        continue;
      }
      $this->documents->upsertCommunicationRelation($documentId, (int) $draft->id(), 'sent_with');
    }
  }

  /** @return array<int,array{filecontent:string,filename:string,filemime:string}> */
  public function resolve(NodeInterface $communication): array {
    $attachments = [];
    $seenHashes = [];
    $totalBytes = 0;

    if ($communication->hasField('field_brebo_comm_attachments')) {
      foreach ($communication->get('field_brebo_comm_attachments')->referencedEntities() as $file) {
        if (!$file instanceof FileInterface) {
          continue;
        }
        $path = $this->fileSystem->realpath($file->getFileUri());
        if (!is_string($path) || !is_readable($path)) {
          throw new \RuntimeException('Een geüploade mailbijlage is niet meer leesbaar.');
        }
        $content = (string) file_get_contents($path);
        $this->append($attachments, $seenHashes, $totalBytes, $content, $file->getFilename(), $file->getMimeType());
      }
    }

    $relations = $this->documents->documentsForCommunication((int) $communication->id());
    foreach ($relations as $relation) {
      if (!in_array((string) ($relation['relation_role'] ?? ''), ['sent_with', 'created_with'], TRUE)) {
        continue;
      }
      $documentId = (int) ($relation['id'] ?? 0);
      $document = $this->database->select('brebo_document', 'd')
        ->fields('d', ['title', 'original_filename', 'mime_type', 'sha256'])
        ->condition('id', $documentId)
        ->condition('lifecycle_status', 'deleted', '<>')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!$document) {
        throw new \RuntimeException('Een gekozen BREBO-document is niet meer beschikbaar.');
      }

      $location = $this->storageLocator->locate($documentId);
      $content = '';
      $filename = trim((string) ($document['original_filename'] ?? $document['title'] ?? 'document')) ?: 'document';
      $mime = trim((string) ($document['mime_type'] ?? 'application/octet-stream')) ?: 'application/octet-stream';

      if (($location['access_mode'] ?? '') === 'local_private') {
        $path = $this->fileSystem->realpath((string) ($location['local_uri'] ?? ''));
        if (is_string($path) && is_readable($path)) {
          $content = (string) file_get_contents($path);
        }
      }
      elseif (($location['access_mode'] ?? '') === 'source_provider') {
        $source = $this->database->select('brebo_document_source', 's')
          ->fields('s', ['source_system'])
          ->condition('document_id', $documentId)
          ->orderBy('source_timestamp_authoritative', 'DESC')
          ->orderBy('id', 'DESC')
          ->range(0, 1)
          ->execute()
          ->fetchAssoc();
        $result = $this->sourceMailboxReader->read((string) ($source['source_system'] ?? ''), (string) ($location['storage_key'] ?? ''));
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

  private function registerUploadedDocument(NodeInterface $draft, FileInterface $file): int {
    $path = $this->fileSystem->realpath($file->getFileUri());
    if (!is_string($path) || !is_readable($path)) {
      throw new \RuntimeException('De geüploade bijlage kon niet worden gecontroleerd.');
    }
    $sha256 = hash_file('sha256', $path);
    $document = $this->documents->upsertDocument([
      'title' => $file->getFilename(),
      'document_type' => 'mail_attachment',
      'original_filename' => $file->getFilename(),
      'mime_type' => $file->getMimeType(),
      'file_size' => (int) $file->getSize(),
      'sha256' => $sha256,
      'storage_provider' => 'drupal_private',
      'storage_key' => $file->getFileUri(),
      'lifecycle_status' => 'active',
    ]);
    $this->documents->addSource((int) $document['id'], [
      'source_system' => 'brebo_outbound_mail',
      'source_external_id' => 'communication:' . $draft->id() . ':file:' . $file->id(),
      'communication_nid' => (int) $draft->id(),
      'source_actor' => (string) $draft->getOwner()->getDisplayName(),
      'source_timestamp' => gmdate(DATE_ATOM),
      'source_timestamp_authoritative' => TRUE,
      'original_filename' => $file->getFilename(),
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
