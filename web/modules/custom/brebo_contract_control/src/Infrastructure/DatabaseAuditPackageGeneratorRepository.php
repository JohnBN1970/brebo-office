<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\AuditPackageGeneratorRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for audit-package generation. */
final class DatabaseAuditPackageGeneratorRepository implements AuditPackageGeneratorRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return list<array<string, mixed>> */
  public function findActivePolicies(string $scope, int $now): array {
    return $this->database
      ->select('brebo_policy_rule', 'p')
      ->fields('p')
      ->condition('scope', $scope)
      ->condition('status', 'active')
      ->condition('effective_from', $now, '<=')
      ->orderBy('policy_code')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return list<array<string, mixed>> */
  public function findEvidence(string $scope): array {
    return $this->database
      ->select('brebo_compliance_evidence', 'e')
      ->fields('e')
      ->condition('scope', $scope)
      ->orderBy('evaluated_at')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param list<int> $policyIds
   * @return list<array<string, mixed>>
   */
  public function findExceptions(array $policyIds): array {
    if ($policyIds === []) {
      return [];
    }

    return $this->database
      ->select('brebo_policy_exception', 'x')
      ->fields('x')
      ->condition('policy_id', $policyIds, 'IN')
      ->orderBy('approved_at')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @param array<string, mixed> $package */
  public function insertPackage(array $package): int {
    return (int) $this->database
      ->insert('brebo_audit_package')
      ->fields($package)
      ->execute();
  }

}
