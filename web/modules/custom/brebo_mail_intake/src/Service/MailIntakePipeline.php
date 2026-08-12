<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Source\MailSourceAdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates normalization output into classification, meaning extraction,
 * relation suggestions and durable Communication registration.
 */
final class MailIntakePipeline {

  public function __construct(
    private readonly MailClassifier $classifier,
    private readonly MailMeaningExtractor $meaningExtractor,
    private readonly MailRelationSuggester $relationSuggester,
    private readonly MailIntakeIngestor $ingestor,
    private readonly LoggerInterface $logger,
    private readonly MailIntakeFailureRegistry $failureRegistry,
  ) {}

  /**
   * Processes every message supplied by one source adapter.
   *
   * One malformed or otherwise unprocessable message must never block the rest
   * of a source batch. Failed items are recorded with a privacy-safe reference
   * and processing continues with the next message.
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
   * Meaning extraction is intentionally advisory: no action, risk, deadline or
   * subtype is promoted to formal dossier truth by this pipeline.
   *
   * @param array<string, mixed> $mail
   *
   * @return array<string, mixed>
   */
  public function process(array $mail): array {
    $subject = trim((string) ($mail['subject'] ?? ''));
    $body = trim((string) ($mail['body'] ?? ''));

    $classification = $this->classifier->classify($subject, $body);
    $meaning = $this->meaningExtractor->extract($subject, $body);
    $relations = $this->relationSuggester->suggest($subject, $body);

    if (trim((string) ($mail['classification'] ?? '')) === '') {
      $mail['classification'] = $classification['classification'];
    }

    if (!isset($mail['match_confidence'])) {
      $mail['match_confidence'] = $relations['confidence'];
    }
    if (trim((string) ($mail['match_basis'] ?? '')) === '') {
      $mail['match_basis'] = implode(' ', [
        $classification['basis'],
        $relations['basis'],
      ]);
    }
    if (empty($mail['suggested_building_id']) && $relations['building_id'] !== NULL) {
      $mail['suggested_building_id'] = $relations['building_id'];
    }
    if (empty($mail['suggested_project_id']) && $relations['project_id'] !== NULL) {
      $mail['suggested_project_id'] = $relations['project_id'];
    }

    $result = $this->ingestor->ingest($mail);
    $result['classification'] = $mail['classification'];
    $result['classification_confidence'] = $classification['confidence'];
    $result['meaning_signals'] = $meaning['signals'];
    $result['meaning_subtypes'] = $meaning['subtypes'];
    $result['meaning_confidence'] = $meaning['confidence'];
    $result['meaning_basis'] = $meaning['basis'];
    $result['suggested_building_id'] = $mail['suggested_building_id'] ?? NULL;
    $result['suggested_project_id'] = $mail['suggested_project_id'] ?? NULL;
    $result['match_confidence'] = $mail['match_confidence'] ?? 0.0;
    $result['requires_human_review'] = TRUE;

    return $result;
  }

  /** @param array<string, mixed> $mail */
  private function sourceReference(array $mail): string {
    $source = trim((string) ($mail['source_id'] ?? $mail['source_hash'] ?? 'unknown'));
    return substr(hash('sha256', $source), 0, 16);
  }

}
