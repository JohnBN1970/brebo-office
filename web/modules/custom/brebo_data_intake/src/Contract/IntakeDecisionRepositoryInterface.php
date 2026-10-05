<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface IntakeDecisionRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function record(int $recordId): ?array;

  public function updateReviewPayload(int $recordId, string $currentPayload, string $newPayload): bool;

  public function transitionReviewStatus(int $recordId, string $currentPayload, string $newStatus): bool;

  /** @param array<string,mixed> $canonical */
  public function audit(int $recordId, string $action, string $previousStatus, string $newStatus, int $actorUid, string $classification, array $canonical, string $note): void;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

}
