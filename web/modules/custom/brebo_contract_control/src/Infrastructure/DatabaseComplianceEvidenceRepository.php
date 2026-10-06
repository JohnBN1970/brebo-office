<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ComplianceEvidenceRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for compliance evidence persistence. */
final class DatabaseComplianceEvidenceRepository implements ComplianceEvidenceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string, mixed> $record */
  public function insert(array $record): int {
    return (int) $this->database
      ->insert('brebo_compliance_evidence')
      ->fields($record)
      ->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function findAuditTrail(string $policyCode, ?string $scope = NULL): array {
    $query = $this->database
      ->select('brebo_compliance_evidence', 'e')
      ->fields('e')
      ->condition('policy_code', $policyCode)
      ->orderBy('evaluated_at', 'DESC');

    if ($scope !== NULL) {
      $query->condition('scope', $scope);
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
