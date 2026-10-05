<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface MasterdataCandidateRepositoryInterface {
  /** @param array<string,mixed> $payload */
  public function propose(int $recordId, string $candidateType, array $payload, ?string $matchedEntityType = NULL, ?string $matchedEntityId = NULL, ?float $confidence = NULL): int;
  public function markMatched(int $candidateId, string $entityType, string $entityId, float $confidence): void;
}
