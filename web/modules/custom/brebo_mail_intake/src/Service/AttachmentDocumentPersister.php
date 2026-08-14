<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\brebo_document_data\Service\DocumentRevisionSeries;

/**
 * Persists mail attachment identity, provenance, context and extracted evidence.
 *
 * Attachment extraction and dossier placement remain proposals. Source mail
 * date/time is authoritative provenance; extracted content never becomes
 * canonical truth automatically.
 */
final class AttachmentDocumentPersister {

  public function __construct(
    private readonly ?DocumentRepository $documentRepository = NULL,
    private readonly ?DocumentRevisionSeries $revisionSeries = NULL,
  ) {}

  /**
   * @param array<string, mixed> $mail
   * @param array<int, array<string, mixed>> $evidence
   *
   * @return array<int, array{document_id:int,source_id:int,state:string,document_family:string,revision_code:?string}>
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
    $matchConfidence = max(0.0, min(1.0, (float) ($mail['match_confidence'] ?? 0.0)));
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
      $revisionProposal = $this->revisionSeries?->proposeIdentity($filename) ?? [
        'family' => '',
        'revision_code' => NULL,
        'confidence' => 0.0,
        'reason' => 'Revision service niet beschikbaar.',
      ];

      $document = $this->documentRepository->upsertDocument([
        'title' => $filename !== '' ? $filename : 'Bijlage',
        'document_type' => 'mail_attachment',
        'document_family' => trim((string) ($revisionProposal['family'] ?? '')) ?: NULL,
        'revision_code' => isset($revisionProposal['revision_code']) ? (string) $revisionProposal['revision_code'] : NULL,
        'original_filename' => $filename,
        'mime_type' => $mime,
        'file_size' => max(0, (int) ($attachment['size'] ?? 0)),
        'sha256' => $sha256,
        'storage_provider' => 'source_mailbox',
        'storage_key' => $sourceId !== '' ? $sourceId . '#part=' . $part : NULL,
        'lifecycle_status' => 'active',
      ]);

      $externalId = implode('|', array_filter([$sourceId, 'part:' . $part, $sha256]));
      $this->documentRepository->upsertCommunicationRelation((int) $document['id'], $communicationNid, 'received_with');
      $source = $this->documentRepository->addSource((int) $document['id'], [
        'source_system' => $sourceSystem,
        'source_external_id' => $externalId,
        'communication_nid' => $communicationNid,
        'source_actor' => $sourceActor,
        'source_timestamp' => $sourceTimestamp,
        'source_timestamp_authoritative' => $sourceTimestamp !== '',
        'original_filename' => $filename,
        'sha256' => $sha256,
        'extraction_method' => (string) ($attachment['extraction_state'] ?? 'metadata_only'),
        'fragment_location' => 'MIME part ' . $part,
        'artifact_role' => 'original',
        'confidence' => 1.0,
        'review_status' => 'unreviewed',
      ]);

      foreach ([
        'building' => (int) ($mail['suggested_building_id'] ?? 0),
        'project' => (int) ($mail['suggested_project_id'] ?? 0),
      ] as $contextType => $contextId) {
        if ($contextId <= 0) {
          continue;
        }
        $this->documentRepository->upsertContext((int) $document['id'], [
          'context_type' => $contextType,
          'context_id' => $contextId,
          'relation_role' => 'supporting',
          'confidence' => $matchConfidence,
          'relation_source' => 'mail_intake_context_proposal',
          'review_status' => 'proposed',
        ]);
      }

      if (trim((string) ($revisionProposal['family'] ?? '')) !== '') {
        $this->documentRepository->addEvidence((int) $document['id'], [
          'source_id' => (int) $source['id'],
          'fragment_location' => 'bestandsnaam',
          'evidence_type' => 'revision_identity_proposal',
          'extracted_value' => json_encode([
            'family' => (string) $revisionProposal['family'],
            'revision_code' => $revisionProposal['revision_code'] ?? NULL,
            'reason' => (string) ($revisionProposal['reason'] ?? ''),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string) $revisionProposal['family'],
          'extraction_method' => 'filename_revision_parser',
          'confidence' => (float) ($revisionProposal['confidence'] ?? 0.0),
          'validation_status' => 'unvalidated',
        ]);
      }

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
        'document_family' => (string) ($revisionProposal['family'] ?? ''),
        'revision_code' => isset($revisionProposal['revision_code']) ? (string) $revisionProposal['revision_code'] : NULL,
      ];
    }

    return $results;
  }

}
