<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

interface ControlActionRepositoryInterface {

  /** @return array<int,array<string,mixed>> */
  public function activeRows(): array;

  /** @return array<int,array<string,mixed>> */
  public function projectActions(int $projectId): array;

  public function byProjectDriver(int $projectId, string $driverCode): ?array;

  public function byId(int $actionId): ?array;

  /** @param array<string,mixed> $fields */
  public function create(int $projectId, string $driverCode, array $fields): int;

  /** @param array<string,mixed> $fields */
  public function update(int $actionId, array $fields): void;

  /** @return array<int,array<string,mixed>> */
  public function overdueRows(int $now): array;

  public function countOpenForProject(int $projectId): int;

  /** @return array<int,array{driver_code:string,project_count:int}> */
  public function recurringDrivers(int $minimumProjects = 2): array;

}
