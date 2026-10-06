<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\AuditPackageVerificationReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for audit-package verification reads. */
final class DatabaseAuditPackageVerificationReadRepository implements AuditPackageVerificationReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed>|null */
  public function findPackage(int $packageId): ?array {
    $record = $this->database
      ->select('brebo_audit_package', 'p')
      ->fields('p')
      ->condition('id', $packageId)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /** @return array<string, mixed>|null */
  public function findEvidence(int $evidenceId): ?array {
    $record = $this->database
      ->select('brebo_compliance_evidence', 'e')
      ->fields('e')
      ->condition('id', $evidenceId)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

}
