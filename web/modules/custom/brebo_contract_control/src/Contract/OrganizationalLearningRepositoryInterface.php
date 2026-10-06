<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for organizational learning. */
interface OrganizationalLearningRepositoryInterface {

  /** @param array<string, mixed> $record */
  public function insert(array $record): int;

  /** @return array<int, array<string, mixed>> */
  public function findDueForReview(int $now): array;

  /** @return array<int, array<string, mixed>> */
  public function findHistory(string $lessonCode): array;

}
