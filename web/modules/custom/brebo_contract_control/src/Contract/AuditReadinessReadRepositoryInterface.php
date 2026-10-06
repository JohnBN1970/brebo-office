<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

interface AuditReadinessReadRepositoryInterface {

  /** @return array<int, array<string, mixed>> */
  public function activePolicyRules(string $scope, int $effectiveAt): array;

  /** @return array<string, mixed>|null */
  public function latestComplianceEvidence(string $policyCode, string $policyVersion, string $scope): ?array;

}
