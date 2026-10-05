<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface DataIngestRepositoryInterface {
  public function registerSource(string $sourceKey, string $label, string $sourceType, string $providerKey, ?string $configRef = NULL): int;
  public function findRecordBySourceIdentity(int $sourceId, string $recordType, string $externalKey, string $status = 'review_required'): ?int;
  /** @param array<string,mixed> $metadata */
  public function startRun(int $sourceId, string $triggerType, ?string $sourceReference = NULL, ?string $sourceHash = NULL, array $metadata = []): int;
  /** @param array<string,mixed> $payload */
  public function addRecord(int $runId, string $recordType, array $payload, ?string $externalKey = NULL, ?string $sourceReference = NULL, ?float $confidence = NULL, string $status = 'normalized'): int;
  /** @param array{record_count?:int,accepted_count?:int,rejected_count?:int,error_count?:int} $counts */
  public function finishRun(int $runId, string $status, array $counts = []): void;
}
