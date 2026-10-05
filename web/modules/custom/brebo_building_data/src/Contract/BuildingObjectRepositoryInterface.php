<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Contract;

interface BuildingObjectRepositoryInterface {

  public function create(int $buildingNid, string $type, string $code, string $label, ?int $parentId = NULL, array $metadata = []): int;

  public function load(int $id): array;

  public function tree(int $buildingNid): array;

  public function ancestors(int $objectId): array;

}
