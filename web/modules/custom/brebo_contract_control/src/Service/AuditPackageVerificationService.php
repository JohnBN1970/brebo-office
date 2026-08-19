<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Verifies frozen audit-package and evidence integrity. */
final class AuditPackageVerificationService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function verify(int $packageId): array {
    $package = $this->database->select('brebo_audit_package', 'p')->fields('p')->condition('id', $packageId)->execute()->fetchAssoc();
    if (!$package) {
      throw new \InvalidArgumentException('Onbekend auditpakket.');
    }

    $manifestJson = (string) $package['manifest_json'];
    $expectedPackageHash = (string) $package['package_hash'];
    $actualPackageHash = hash('sha256', $manifestJson);
    $manifest = json_decode($manifestJson, TRUE, 512, JSON_THROW_ON_ERROR);

    $evidenceChecks = [];
    $evidenceOk = TRUE;
    foreach ((array) ($manifest['evidence_register'] ?? []) as $entry) {
      $record = $this->database->select('brebo_compliance_evidence', 'e')->fields('e')->condition('id', (int) ($entry['id'] ?? 0))->execute()->fetchAssoc();
      if (!$record) {
        $evidenceOk = FALSE;
        $evidenceChecks[] = ['id' => (int) ($entry['id'] ?? 0), 'status' => 'missing'];
        continue;
      }
      $actualEvidenceHash = hash('sha256', (string) $record['evidence_json']);
      $storedEvidenceHash = (string) $record['evidence_hash'];
      $manifestEvidenceHash = (string) ($entry['evidence_hash'] ?? '');
      $ok = hash_equals($storedEvidenceHash, $actualEvidenceHash) && hash_equals($manifestEvidenceHash, $storedEvidenceHash);
      $evidenceOk = $evidenceOk && $ok;
      $evidenceChecks[] = ['id' => (int) $record['id'], 'status' => $ok ? 'valid' : 'hash_mismatch'];
    }

    $packageOk = hash_equals($expectedPackageHash, $actualPackageHash);
    return [
      'package_id' => $packageId,
      'package_ref' => $package['package_ref'],
      'package_hash_valid' => $packageOk,
      'evidence_hashes_valid' => $evidenceOk,
      'integrity_status' => $packageOk && $evidenceOk ? 'verified' : 'integrity_failure',
      'evidence_checks' => $evidenceChecks,
    ];
  }
}
