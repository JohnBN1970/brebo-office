<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for audit-package export. */
interface AuditPackageExportReadRepositoryInterface {

  /** @return array<string, mixed>|null */
  public function findPackage(int $packageId): ?array;

}
