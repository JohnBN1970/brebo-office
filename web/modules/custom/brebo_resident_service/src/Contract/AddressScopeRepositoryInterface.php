<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Contract;

/** Persistence boundary for address-scope intake and materialization. */
interface AddressScopeRepositoryInterface {

  /** @param array<string,mixed> $scope */
  public function createIntake(
    array $scope,
    string $sourceText,
    string $sourceType,
    ?string $sourceId,
    ?int $buildingNid,
    ?int $projectId,
    ?int $uid,
    int $createdAt,
  ): int;

  /** @param array<int,array<string,mixed>> $resolved */
  public function markResolved(int $intakeId, array $resolved, int $resolvedAt): void;

  public function markError(int $intakeId, string $message, int $resolvedAt): void;

  /** @return array<string,mixed>|null */
  public function intake(int $intakeId): ?array;

  /** @param array<string,mixed> $address */
  public function ensureBuildingAddress(int $buildingNid, int $intakeId, string $normalizedKey, array $address, int $now): int;

  /** @param array<string,mixed> $address */
  public function residenceExists(int $buildingNid, int $buildingAddressId, array $address): bool;

  /** @param array<string,mixed> $address */
  public function createResidence(
    int $buildingNid,
    int $buildingAddressId,
    int $intakeId,
    array $address,
    ?int $projectId,
    string $addressLine,
    int $now,
  ): void;

  public function markMaterialized(int $intakeId, int $buildingNid, ?int $projectId, int $materializedAt): void;

}
