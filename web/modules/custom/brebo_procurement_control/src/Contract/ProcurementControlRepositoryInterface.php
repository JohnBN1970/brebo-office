<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Contract;

/** Persistence/read boundary for procurement control data. */
interface ProcurementControlRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function decision(int $decisionId): ?array;

  /** @param array<string,mixed> $fields */
  public function createDecision(array $fields): int;

  /** @return array<string,mixed>|null */
  public function awardByDecision(int $decisionId): ?array;

  /** @param array<string,mixed> $fields */
  public function createAward(array $fields): int;

  public function outcomeExists(int $decisionId): bool;

  /** @param array<string,mixed> $fields */
  public function createOutcome(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function upsertOutcome(int $decisionId, array $fields): void;

  /** @return list<array<string,mixed>> */
  public function outcomes(): array;

  /** @return list<array<string,mixed>> */
  public function decisionOutcomeRows(): array;

  /** @param array<string,mixed> $fields */
  public function upsertDecisionContext(int $decisionId, array $fields): void;

  /** @return list<array<string,mixed>> */
  public function contextOutcomeRows(): array;

  /** @param array<string,mixed> $fields */
  public function createModelReview(array $fields): int;

  /** @return array<string,mixed>|null */
  public function modelReview(int $reviewId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateModelReview(int $reviewId, array $fields): void;

}
