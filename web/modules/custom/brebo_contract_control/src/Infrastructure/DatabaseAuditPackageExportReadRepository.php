<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\AuditPackageExportReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for audit-package export reads. */
final class DatabaseAuditPackageExportReadRepository implements AuditPackageExportReadRepositoryInterface {

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

}
