<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Persistence boundary for OnSite assignment, location and evidence data. */
interface OnSiteRepositoryInterface {

  /** @return int[] */
  public function projectIdsForUser(int $userId, string $date): array;

  /** @return array<int,array{id:int,name:string}> */
  public function projects(array $projectIds): array;

  /** @return array<int,array<string,mixed>> */
  public function clockLocations(): array;

  /** @return array{id:int,building_id:int,project_id:int}|null */
  public function clockZone(int $zoneId): ?array;

  public function validBuilding(int $buildingId): bool;

  public function validProject(int $projectId): bool;

  /** @param array<string,mixed> $event */
  public function createPresenceEvent(array $event): int;

}
