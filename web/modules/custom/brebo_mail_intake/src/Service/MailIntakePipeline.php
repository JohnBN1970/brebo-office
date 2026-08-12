<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Source\MailSourceAdapterInterface;

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
  ) {}

  /**
   * Processes every message supplied by one source adapter.
   *
   * @return array<int, array<string, mixed>>
   */
  public function processSource(MailSourceAdapterInterface $adapter): array {
    $results = [];
    foreach ($adapter->messages() as $mail) {
      $results[] = $this->process($mail);
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

}
