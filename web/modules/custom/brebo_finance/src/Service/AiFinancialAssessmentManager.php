<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Persists auditable AI advice without granting autonomous financial authority.
 */
final class AiFinancialAssessmentManager {

  public function __construct(private readonly Connection $database) {}

  /**
   * Stores one model output with its exact evidence and provenance.
   */
  public function record(
    int $projectNid,
    string $assessmentType,
    string $sourceType,
    int $sourceId,
    string $modelProvider,
    string $modelName,
    ?string $modelVersion,
    string $promptVersion,
    string $confidence,
    string $severity,
    string $title,
    string $analysis,
    string $recommendation,
    array $evidence,
    array $output,
    int $systemUserId = 0,
  ): int {
    if (!preg_match('/^(?:0(?:\.\d{1,6})?|1(?:\.0{1,6})?)$/', trim($confidence))) {
      throw new InvalidArgumentException('AI confidence must be between 0 and 1 with at most six decimals.');
    }
    if (!in_array($severity, ['critical', 'high', 'medium', 'low'], TRUE)) {
      throw new InvalidArgumentException('Unknown AI assessment severity.');
    }
    foreach ([
      $assessmentType,
      $sourceType,
      $modelProvider,
      $modelName,
      $promptVersion,
      $title,
      $analysis,
      $recommendation,
    ] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('AI assessment metadata and explanation are required.');
      }
    }
    if ($evidence === []) {
      throw new InvalidArgumentException('AI advice without explicit evidence is not accepted.');
    }

    $evidenceJson = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $outputJson = json_encode($output, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $now = time();

    return (int) $this->database->insert('brebo_finance_ai_assessment')
      ->fields([
        'project_nid' => $projectNid,
        'assessment_type' => trim($assessmentType),
        'source_type' => trim($sourceType),
        'source_id' => $sourceId,
        'model_provider' => trim($modelProvider),
        'model_name' => trim($modelName),
        'model_version' => $modelVersion !== NULL ? trim($modelVersion) : NULL,
        'prompt_version' => trim($promptVersion),
        'confidence' => str_pad(trim($confidence), 1, '0'),
        'severity' => $severity,
        'title' => trim($title),
        'analysis' => trim($analysis),
        'recommendation' => trim($recommendation),
        'evidence_payload' => $evidenceJson,
        'output_payload' => $outputJson,
        'input_hash' => hash('sha256', $evidenceJson),
        'output_hash' => hash('sha256', $outputJson),
        'status' => 'pending_review',
        'created' => $now,
        'created_by' => $systemUserId,
        'changed' => $now,
        'changed_by' => $systemUserId,
      ])
      ->execute();
  }

  /**
   * Accepts or rejects AI advice; acceptance creates a human-owned finding.
   */
  public function review(
    int $assessmentId,
    string $decision,
    string $note,
    ?int $ownerUid,
    ?string $dueDate,
    int $reviewerUid,
  ): ?int {
    if (!in_array($decision, ['accepted', 'rejected'], TRUE)) {
      throw new InvalidArgumentException('AI review decision must be accepted or rejected.');
    }
    if (trim($note) === '') {
      throw new InvalidArgumentException('AI review requires a human note.');
    }
    if ($decision === 'accepted' && $ownerUid === NULL) {
      throw new InvalidArgumentException('Accepted AI advice requires a human owner.');
    }

    $assessment = $this->database->select('brebo_finance_ai_assessment', 'a')
      ->fields('a')
      ->condition('id', $assessmentId)
      ->execute()
      ->fetchAssoc();
    if ($assessment === FALSE || $assessment['status'] !== 'pending_review') {
      throw new UnexpectedValueException('A pending AI assessment is required.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $now = time();
      $findingId = NULL;
      if ($decision === 'accepted') {
        $findingId = (int) $this->database->insert('brebo_finance_control_finding')
          ->fields([
            'project_nid' => $assessment['project_nid'],
            'control_code' => 'AI-' . strtoupper(substr(hash('sha256', (string) $assessmentId), 0, 12)),
            'origin' => 'ai_reviewed',
            'severity' => $assessment['severity'],
            'source_type' => $assessment['source_type'],
            'source_id' => $assessment['source_id'],
            'title' => $assessment['title'],
            'cause' => $assessment['analysis'],
            'consequence' => 'AI-signaal is door een bevoegde medewerker relevant verklaard; menselijke verificatie en opvolging zijn vereist.',
            'control_measure' => $assessment['recommendation'],
            'owner_uid' => $ownerUid,
            'due_date' => $dueDate,
            'status' => 'open',
            'detected' => $now,
            'last_seen' => $now,
            'payload' => json_encode([
              'assessment_id' => $assessmentId,
              'confidence' => $assessment['confidence'],
              'input_hash' => $assessment['input_hash'],
              'output_hash' => $assessment['output_hash'],
              'human_review_note' => trim($note),
            ], JSON_THROW_ON_ERROR),
            'created' => $now,
            'created_by' => $reviewerUid,
            'changed' => $now,
            'changed_by' => $reviewerUid,
          ])
          ->execute();
      }

      $this->database->update('brebo_finance_ai_assessment')
        ->fields([
          'status' => $decision,
          'control_finding_id' => $findingId,
          'reviewed' => $now,
          'reviewed_by' => $reviewerUid,
          'review_note' => trim($note),
          'changed' => $now,
          'changed_by' => $reviewerUid,
        ])
        ->condition('id', $assessmentId)
        ->execute();

      return $findingId;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
