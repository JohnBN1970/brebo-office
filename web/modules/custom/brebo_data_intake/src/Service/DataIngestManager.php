<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Coordinates source registration, ingest runs and normalized records. */
final class DataIngestManager {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function registerSource(string $sourceKey, string $label, string $sourceType, string $providerKey, ?string $configRef = NULL): int {
    $sourceKey = trim($sourceKey);
    $label = trim($label);
    $sourceType = trim($sourceType);
    $providerKey = trim($providerKey);
    if ($sourceKey === '' || $label === '' || $sourceType === '' || $providerKey === '') {
      throw new \InvalidArgumentException('Data source requires key, label, type and provider.');
    }
    $now = $this->time->getRequestTime();
    $fields = [
      'label' => mb_substr($label, 0, 255),
      'source_type' => mb_substr($sourceType, 0, 32),
      'provider_key' => mb_substr($providerKey, 0, 96),
      'config_ref' => $configRef !== NULL ? mb_substr($configRef, 0, 255) : NULL,
      'active' => 1,
      'changed' => $now,
    ];
    $this->database->merge('brebo_data_source')
      ->keys(['source_key' => mb_substr($sourceKey, 0, 96)])
      ->fields($fields)
      ->insertFields(['created' => $now] + $fields)
      ->execute();
    return (int) $this->database->select('brebo_data_source', 's')->fields('s', ['id'])->condition('source_key', $sourceKey)->execute()->fetchField();
  }

  /**
   * Finds an existing normalized record for one source and source identity.
   */
  public function findRecordBySourceIdentity(int $sourceId, string $recordType, string $externalKey, string $status = 'review_required'): ?int {
    if ($sourceId <= 0 || trim($recordType) === '' || trim($externalKey) === '') {
      return NULL;
    }

    $query = $this->database->select('brebo_data_record', 'r');
    $query->innerJoin('brebo_data_ingest_run', 'run', 'run.id = r.run_id');
    $recordId = $query
      ->fields('r', ['id'])
      ->condition('run.source_id', $sourceId)
      ->condition('r.record_type', mb_substr(trim($recordType), 0, 64))
      ->condition('r.external_key', mb_substr(trim($externalKey), 0, 255))
      ->condition('r.status', mb_substr(trim($status), 0, 32))
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $recordId ? (int) $recordId : NULL;
  }

  /** @param array<string,mixed> $metadata */
  public function startRun(int $sourceId, string $triggerType, ?string $sourceReference = NULL, ?string $sourceHash = NULL, array $metadata = []): int {
    if ($sourceId <= 0 || trim($triggerType) === '') {
      throw new \InvalidArgumentException('Ingest run requires a source and trigger type.');
    }
    if ($sourceHash !== NULL && !preg_match('/^[a-f0-9]{64}$/i', $sourceHash)) {
      throw new \InvalidArgumentException('Source hash must be a SHA-256 value.');
    }
    return (int) $this->database->insert('brebo_data_ingest_run')->fields([
      'source_id' => $sourceId,
      'trigger_type' => mb_substr(trim($triggerType), 0, 32),
      'source_reference' => $sourceReference !== NULL ? mb_substr($sourceReference, 0, 512) : NULL,
      'source_hash' => $sourceHash,
      'status' => 'processing',
      'started' => $this->time->getRequestTime(),
      'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
    ])->execute();
  }

  /** @param array<string,mixed> $payload */
  public function addRecord(int $runId, string $recordType, array $payload, ?string $externalKey = NULL, ?string $sourceReference = NULL, ?float $confidence = NULL, string $status = 'normalized'): int {
    $recordType = trim($recordType);
    $status = trim($status);
    if ($runId <= 0 || $recordType === '') {
      throw new \InvalidArgumentException('Normalized record requires run and record type.');
    }
    if ($confidence !== NULL && ($confidence < 0 || $confidence > 1)) {
      throw new \InvalidArgumentException('Confidence must be between 0 and 1.');
    }
    if (!in_array($status, ['normalized', 'review_required', 'accepted', 'rejected'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported data record status.');
    }
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', $recordType . "\n" . ($externalKey ?? '') . "\n" . $json);
    $existing = $this->database->select('brebo_data_record', 'r')->fields('r', ['id'])->condition('run_id', $runId)->condition('content_hash', $hash)->execute()->fetchField();
    if ($existing) {
      return (int) $existing;
    }
    return (int) $this->database->insert('brebo_data_record')->fields([
      'run_id' => $runId,
      'record_type' => mb_substr($recordType, 0, 64),
      'external_key' => $externalKey !== NULL ? mb_substr($externalKey, 0, 255) : NULL,
      'source_reference' => $sourceReference !== NULL ? mb_substr($sourceReference, 0, 512) : NULL,
      'content_hash' => $hash,
      'payload' => $json,
      'status' => $status,
      'confidence' => $confidence,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  /** @param array{record_count?:int,accepted_count?:int,rejected_count?:int,error_count?:int} $counts */
  public function finishRun(int $runId, string $status, array $counts = []): void {
    if (!in_array($status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported ingest run status.');
    }
    $this->database->update('brebo_data_ingest_run')->fields([
      'status' => $status,
      'finished' => $this->time->getRequestTime(),
      'record_count' => max(0, (int) ($counts['record_count'] ?? 0)),
      'accepted_count' => max(0, (int) ($counts['accepted_count'] ?? 0)),
      'rejected_count' => max(0, (int) ($counts['rejected_count'] ?? 0)),
      'error_count' => max(0, (int) ($counts['error_count'] ?? 0)),
    ])->condition('id', $runId)->execute();
  }

}
