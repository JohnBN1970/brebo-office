<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\AuditReadinessReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseAuditReadinessReadRepository implements AuditReadinessReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function activePolicyRules(string $scope, int $effectiveAt): array {
    return $this->database
      ->select('brebo_policy_rule', 'p')
      ->fields('p')
      ->condition('scope', $scope)
      ->condition('status', 'active')
      ->condition('effective_from', $effectiveAt, '<=')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function latestComplianceEvidence(string $policyCode, string $policyVersion, string $scope): ?array {
    $row = $this->database
      ->select('brebo_compliance_evidence', 'e')
      ->fields('e')
      ->condition('policy_code', $policyCode)
      ->condition('policy_version', $policyVersion)
      ->condition('scope', $scope)
      ->orderBy('evaluated_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

}
