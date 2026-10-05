<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Persistence boundary for project billing-instalment presentation workflows. */
interface ProjectInstalmentRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function instalmentForProject(int $instalmentId, int $projectId): ?array;

  public function instalmentCount(int $projectId): int;

  /** @param array<string,mixed> $fields */
  public function updateInstalmentForProject(int $instalmentId, int $projectId, array $fields): bool;

  /** @param array<string,mixed> $fields */
  public function saveCommercialSchedule(int $projectId, array $fields, int $now, int $actorUid): void;

}
