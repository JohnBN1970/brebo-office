<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Contract;

interface BuildingRelationRepositoryInterface {

  public function upsertAddress(int $buildingNid, array $address): array;

  public function upsertBagIdentity(int $buildingNid, string $bagType, string $bagId, array $metadata = []): array;

  public function clearSourceRelations(int $buildingNid, string $source): array;

  /** @return int[] */
  public function findBuildingIdsByAddress(array $address): array;

  /** @return int[] */
  public function findBuildingIdsByBagIdentity(string $bagType, string $bagId): array;

  public function resolveBuildingCandidate(array $candidate): array;

  /** @return array<int,array<string,mixed>> */
  public function addressesForBuilding(int $buildingNid): array;

  /** @return array<int,array<string,mixed>> */
  public function bagIdentitiesForBuilding(int $buildingNid): array;

}
