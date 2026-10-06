<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for compliance evidence. */
interface ComplianceEvidenceRepositoryInterface {

  /** @param array<string, mixed> $record */
  public function insert(array $record): int;

  /** @return array<int, array<string, mixed>> */
  public function findAuditTrail(string $policyCode, ?string $scope = NULL): array;

}
