<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_document_data\Service\DocumentRepository;

/**
 * Persists mail attachment identity, provenance and extracted evidence.
 *
 * Attachment extraction remains non-canonical. The document repository stores
 * traceability so queue processing cannot discard the evidence that influenced
 * a context proposal.
 */
final class AttachmentDocumentPersister {

  public function __construct(
    private readonly ?DocumentRepository $documentRepository = NULL,
  ) {}

  /**
   * @param array<string, mixed> $mail
   * @param array<int, array<string, mixed>> $evidence
   *
   * @return array<int, array{document_id:int,source_id:int,state:string}>
   */
  public function persist(array $mail, int $communicationNid, array $evidence): array {
    if ($this->documentRepository === NULL || $communicationNid <= 0) {
      return [];
    }

    $attachments = is_array($mail['attachments'] ?? NULL) ? $mail['attachments'] : [];
    if ($attachments === []) {
      return [];
    }

    $sourceSystem = trim((string) ($mail['source_system'] ?? 'mail_intake')) ?: 'mail_intake';
    $sourceId = trim((string) ($mail['source_id'] ?? $mail['source_hash'] ?? ''));
    $sourceActor = trim((string) ($mail['from'] ?? ''));
    $sourceTimestamp = trim((string) ($mail['received_at'] ?? ''));
    $results = [];

    foreach ($attachments as $index => $attachment) {
      if (!is_array($attachment)) {
        continue;
      }
      $sha256 = strtolower(trim((string) ($attachment['sha256'] ?? '')));
      if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        continue;
      }

      $filename = trim((string) ($attachment['filename'] ?? ('bijlage-' . ($index + 1))));
      $mime = trim((string) ($attachment['mime_type'] ?? 'application/octet-stream'));
      $part = trim((string) ($attachment['source_part'] ?? (string) ($index + 1)));
      $document = $this->documentRepository->upsertDocument([
        'title' => $filename !== '' ? $filename : 'Bijlage',
        'document_type' => 'mail_attachment',
        'original_filename' => $filename,
        'mime_type' => $mime,
        'file_size' => max(0, (int) ($attachment['size'] ?? 0)),
        'sha256' => $sha256,
        'storage_provider' => 'source_mailbox',
        'storage_key' => $sourceId !== '' ? $sourceId . '#part=' . $part : NULL,
        'lifecycle_status' => 'active',
      ]);

      $externalId = implode('|', array_filter([$sourceId, 'part:' . $part, $sha256]));
      $source = $this->documentRepository->addSource((int) $document['id'], [
        'source_system' => $sourceSystem,
        'source_external_id' => $externalId,
        'communication_nid' => $communicationNid,
        'source_actor' => $sourceActor,
        'source_timestamp' => $sourceTimestamp,
        'original_filename' => $filename,
        'sha256' => $sha256,
        'extraction_method' => (string) ($attachment['extraction_state'] ?? 'metadata_only'),
        'fragment_location' => 'MIME part ' . $part,
        'artifact_role' => 'original',
        'confidence' => 1.0,
        'review_status' => 'unreviewed',
      ]);

      foreach ($evidence as $fragment) {
        if (!is_array($fragment) || strtolower(trim((string) ($fragment['sha256'] ?? ''))) !== $sha256) {
          continue;
        }
        $text = trim((string) ($fragment['text'] ?? ''));
        if ($text === '') {
          continue;
        }
        $page = isset($fragment['page']) && is_numeric($fragment['page']) ? (int) $fragment['page'] : NULL;
        $this->documentRepository->addEvidence((int) $document['id'], [
          'source_id' => (int) $source['id'],
          'fragment_location' => $page !== NULL ? 'pagina ' . $page : 'bijlage',
          'evidence_type' => 'document_text',
          'extracted_value' => $text,
          'extraction_method' => (string) ($attachment['extraction_state'] ?? 'extracted'),
          'confidence' => (float) ($fragment['confidence'] ?? 0.0),
          'validation_status' => 'unvalidated',
        ]);
      }

      $results[] = [
        'document_id' => (int) $document['id'],
        'source_id' => (int) $source['id'],
        'state' => (string) $document['state'],
      ];
    }

    return $results;
  }

}
