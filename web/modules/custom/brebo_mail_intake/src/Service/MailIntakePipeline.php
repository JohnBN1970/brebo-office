<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Source\MailSourceAdapterInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates normalization output into classification, canonical context
 * resolution and durable Communication registration.
 */
final class MailIntakePipeline {

  public function __construct(
    private readonly MailClassifier $classifier,
    private readonly MailMeaningExtractor $meaningExtractor,
    private readonly MailRelationSuggester $relationSuggester,
    private readonly MailIntakeIngestor $ingestor,
    private readonly LoggerInterface $logger,
    private readonly MailIntakeFailureRegistry $failureRegistry,
    private readonly CanonicalContextResolver $canonicalContextResolver,
    private readonly CanonicalCrmContextResolver $canonicalCrmContextResolver,
    private readonly ProvisionalContextMaterializer $provisionalContextMaterializer,
    private readonly AttachmentEvidenceExtractor $attachmentEvidenceExtractor,
  ) {}

  /**
   * Processes every message supplied by one source adapter.
   *
   * @return array<int, array<string, mixed>>
   */
  public function processSource(MailSourceAdapterInterface $adapter): array {
    $results = [];
    foreach ($adapter->messages() as $mail) {
      try {
        $results[] = $this->process($mail);
      }
      catch (\Throwable $exception) {
        $sourceReference = $this->sourceReference($mail);
        $this->failureRegistry->record($sourceReference, $exception::class);
        $this->logger->error('Mail Intake item failed and was isolated. Source ref: {source_ref}; exception: {exception_type}.', [
          'source_ref' => $sourceReference,
          'exception_type' => $exception::class,
        ]);
        $results[] = [
          'status' => 'error',
          'error_code' => 'mail_intake_item_failed',
          'source_ref' => $sourceReference,
          'exception_type' => $exception::class,
          'requires_human_review' => TRUE,
        ];
      }
    }
    return $results;
  }

  /**
   * Processes one normalized message without establishing uncertain relations.
   *
   * Attachment text may strengthen context resolution, but remains evidence:
   * it is traceable to filename/page/hash and never becomes canonical truth on
   * its own. Unknown context still results only in unpublished review objects.
   *
   * @param array<string, mixed> $mail
   *
   * @return array<string, mixed>
   */
  public function process(array $mail): array {
    $subject = trim((string) ($mail['subject'] ?? ''));
    $body = trim((string) ($mail['body'] ?? ''));
    $attachmentEvidence = $this->attachmentEvidenceExtractor->extract($mail);
    $contextBody = trim(implode("\n\n", array_filter([
      $body,
      (string) ($attachmentEvidence['context_text'] ?? ''),
    ])));

    $classification = $this->classifier->classify($subject, $body);
    $meaning = $this->meaningExtractor->extract($subject, $contextBody);
    $relations = $this->relationSuggester->suggest($subject, $contextBody);
    $canonicalContext = $this->canonicalContextResolver->resolve($subject, $contextBody);
    $crmContext = $this->canonicalCrmContextResolver->resolve($mail);

    if (trim((string) ($mail['classification'] ?? '')) === '') {
      $mail['classification'] = $classification['classification'];
    }

    if (!isset($mail['match_confidence'])) {
      $mail['match_confidence'] = $relations['confidence'];
    }
    if (trim((string) ($mail['match_basis'] ?? '')) === '') {
      $mail['match_basis'] = implode(' ', array_filter([
        $classification['basis'],
        $relations['basis'],
        (string) ($canonicalContext['basis'] ?? ''),
        (string) ($crmContext['basis'] ?? ''),
        (string) ($attachmentEvidence['basis'] ?? ''),
      ]));
    }

    if (!empty($canonicalContext['building_id'])) {
      $mail['suggested_building_id'] = (int) $canonicalContext['building_id'];
    }
    elseif (empty($mail['suggested_building_id']) && $relations['building_id'] !== NULL) {
      $mail['suggested_building_id'] = $relations['building_id'];
    }

    if (!empty($canonicalContext['project_id'])) {
      $mail['suggested_project_id'] = (int) $canonicalContext['project_id'];
    }
    elseif (empty($mail['suggested_project_id']) && $relations['project_id'] !== NULL) {
      $mail['suggested_project_id'] = $relations['project_id'];
    }

    $result = $this->ingestor->ingest($mail);

    $provisionalContext = ['project_id' => NULL, 'building_id' => NULL];
    if (($result['state'] ?? NULL) === 'created'
      && (($canonicalContext['project_state'] ?? NULL) === 'provisional_required'
        || ($canonicalContext['building_state'] ?? NULL) === 'provisional_required')) {
      $communication = \Drupal::entityTypeManager()->getStorage('node')->load((int) ($result['node_id'] ?? 0));
      if (!$communication instanceof NodeInterface) {
        throw new \RuntimeException('Aangemaakte BREBO Communication kon niet worden geladen voor voorlopige context.');
      }
      $provisionalContext = $this->provisionalContextMaterializer->materialize($communication, $canonicalContext);
    }

    $result['classification'] = $mail['classification'];
    $result['classification_confidence'] = $classification['confidence'];
    $result['meaning_signals'] = $meaning['signals'];
    $result['meaning_subtypes'] = $meaning['subtypes'];
    $result['meaning_confidence'] = $meaning['confidence'];
    $result['meaning_basis'] = $meaning['basis'];
    $result['suggested_building_id'] = $mail['suggested_building_id'] ?? NULL;
    $result['suggested_project_id'] = $mail['suggested_project_id'] ?? NULL;
    $result['match_confidence'] = $mail['match_confidence'] ?? 0.0;
    $result['canonical_project_state'] = $canonicalContext['project_state'] ?? NULL;
    $result['canonical_building_state'] = $canonicalContext['building_state'] ?? NULL;
    $result['provisional_project_id'] = $provisionalContext['project_id'];
    $result['provisional_building_id'] = $provisionalContext['building_id'];
    $result['canonical_contact_id'] = $crmContext['contact_id'] ?? NULL;
    $result['canonical_contact_state'] = $crmContext['contact_state'] ?? 'unknown';
    $result['canonical_organization_id'] = $crmContext['organization_id'] ?? NULL;
    $result['canonical_organization_state'] = $crmContext['organization_state'] ?? 'unknown';
    $result['crm_match_confidence'] = $crmContext['confidence'] ?? 0.0;
    $result['crm_match_basis'] = $crmContext['basis'] ?? '';
    $result['crm_matched_email'] = $crmContext['matched_email'] ?? '';
    $result['crm_matched_domain'] = $crmContext['matched_domain'] ?? '';
    $result['attachment_count'] = $attachmentEvidence['attachment_count'] ?? 0;
    $result['attachment_evidence_count'] = count($attachmentEvidence['evidence'] ?? []);
    $result['attachment_evidence'] = $attachmentEvidence['evidence'] ?? [];
    $result['attachment_evidence_basis'] = $attachmentEvidence['basis'] ?? '';
    $result['requires_human_review'] = TRUE;

    return $result;
  }

  /** @param array<string, mixed> $mail */
  private function sourceReference(array $mail): string {
    $source = trim((string) ($mail['source_id'] ?? $mail['source_hash'] ?? 'unknown'));
    return substr(hash('sha256', $source), 0, 16);
  }

}
