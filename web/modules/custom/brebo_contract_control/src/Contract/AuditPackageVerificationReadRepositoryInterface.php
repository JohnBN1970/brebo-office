<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for frozen audit-package verification. */
interface AuditPackageVerificationReadRepositoryInterface {

  /** @return array<string, mixed>|null */
  public function findPackage(int $packageId): ?array;

  /** @return array<string, mixed>|null */
  public function findEvidence(int $evidenceId): ?array;

}
