<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Contract;

interface ProjectBuildingRepositoryInterface {
  public function attach(int $projectNid, int $buildingNid, string $role = 'scope'): void;
  /** @return array<int,array<string,mixed>> */
  public function buildingsForProject(int $projectNid): array;
  /** @return array<int,array<string,mixed>> */
  public function projectsForBuilding(int $buildingNid): array;
  public function contains(int $projectNid, int $buildingNid): bool;
}
