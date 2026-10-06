<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for audit-package generation. */
interface AuditPackageGeneratorRepositoryInterface {

  /** @return list<array<string, mixed>> */
  public function findActivePolicies(string $scope, int $now): array;

  /** @return list<array<string, mixed>> */
  public function findEvidence(string $scope): array;

  /**
   * @param list<int> $policyIds
   * @return list<array<string, mixed>>
   */
  public function findExceptions(array $policyIds): array;

  /** @param array<string, mixed> $package */
  public function insertPackage(array $package): int;

}
