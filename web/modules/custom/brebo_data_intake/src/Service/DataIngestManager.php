<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\DataIngestRepositoryInterface;

/** Coordinates source registration, ingest runs and normalized records. */
final class DataIngestManager {

  public function __construct(private readonly DataIngestRepositoryInterface $repository) {}

  public function registerSource(string $sourceKey, string $label, string $sourceType, string $providerKey, ?string $configRef = NULL): int {
    return $this->repository->registerSource($sourceKey, $label, $sourceType, $providerKey, $configRef);
  }

  public function findRecordBySourceIdentity(int $sourceId, string $recordType, string $externalKey, string $status = 'review_required'): ?int {
    return $this->repository->findRecordBySourceIdentity($sourceId, $recordType, $externalKey, $status);
  }

  /** @param array<string,mixed> $metadata */
  public function startRun(int $sourceId, string $triggerType, ?string $sourceReference = NULL, ?string $sourceHash = NULL, array $metadata = []): int {
    return $this->repository->startRun($sourceId, $triggerType, $sourceReference, $sourceHash, $metadata);
  }

  /** @param array<string,mixed> $payload */
  public function addRecord(int $runId, string $recordType, array $payload, ?string $externalKey = NULL, ?string $sourceReference = NULL, ?float $confidence = NULL, string $status = 'normalized'): int {
    return $this->repository->addRecord($runId, $recordType, $payload, $externalKey, $sourceReference, $confidence, $status);
  }

  /** @param array{record_count?:int,accepted_count?:int,rejected_count?:int,error_count?:int} $counts */
  public function finishRun(int $runId, string $status, array $counts = []): void {
    $this->repository->finishRun($runId, $status, $counts);
  }

}
