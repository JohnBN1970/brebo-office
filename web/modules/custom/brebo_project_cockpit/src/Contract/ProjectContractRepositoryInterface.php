<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Persistence boundary for project-contract truth and approval evidence. */
interface ProjectContractRepositoryInterface {

  /** @return array<string,mixed> */
  public function contract(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function obligations(int $projectId): array;

  public function contractNumberUsedByOtherProject(string $contractNumber, int $projectId): bool;

  /**
   * Saves a draft contract. Returns FALSE when an existing draft could not be
   * updated because its state changed concurrently.
   *
   * @param array<string,mixed> $fields
   */
  public function saveDraft(int $projectId, array $fields, int $now, int $actorUid): bool;

  /** @return array<string,mixed>|null */
  public function commercialScheduleRecord(int $projectId): ?array;

  /** @param array<string,mixed> $fields */
  public function approveDraft(int $contractId, array $fields): bool;

}
