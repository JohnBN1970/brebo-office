<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Creates reviewable proposals before source data mutates BREBO masterdata. */
final class MasterdataCandidateRepository {
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /** @param array<string,mixed> $payload */
  public function propose(int $recordId, string $candidateType, array $payload, ?string $matchedEntityType = NULL, ?string $matchedEntityId = NULL, ?float $confidence = NULL): int {
    $candidateType = trim($candidateType);
    if ($recordId <= 0 || $candidateType === '') {
      throw new \InvalidArgumentException('Masterdata candidate requires record and type.');
    }
    if ($confidence !== NULL && ($confidence < 0 || $confidence > 1)) {
      throw new \InvalidArgumentException('Confidence must be between 0 and 1.');
    }
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $fingerprint = hash('sha256', $candidateType . "\n" . $json);
    $now = $this->time->getRequestTime();
    $this->database->merge('brebo_masterdata_candidate')
      ->keys(['record_id' => $recordId, 'candidate_type' => mb_substr($candidateType, 0, 64), 'fingerprint' => $fingerprint])
      ->fields([
        'matched_entity_type' => $matchedEntityType !== NULL ? mb_substr($matchedEntityType, 0, 64) : NULL,
        'matched_entity_id' => $matchedEntityId !== NULL ? mb_substr($matchedEntityId, 0, 128) : NULL,
        'confidence' => $confidence,
        'payload' => $json,
        'changed' => $now,
      ])
      ->insertFields(['status' => 'proposed', 'created' => $now])
      ->execute();
    return (int) $this->database->select('brebo_masterdata_candidate', 'c')
      ->fields('c', ['id'])
      ->condition('record_id', $recordId)
      ->condition('candidate_type', $candidateType)
      ->condition('fingerprint', $fingerprint)
      ->execute()->fetchField();
  }

  public function markMatched(int $candidateId, string $entityType, string $entityId, float $confidence): void {
    if ($confidence < 0 || $confidence > 1) {
      throw new \InvalidArgumentException('Confidence must be between 0 and 1.');
    }
    $this->database->update('brebo_masterdata_candidate')->fields([
      'matched_entity_type' => mb_substr(trim($entityType), 0, 64),
      'matched_entity_id' => mb_substr(trim($entityId), 0, 128),
      'confidence' => $confidence,
      'status' => 'matched',
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $candidateId)->execute();
  }
}
