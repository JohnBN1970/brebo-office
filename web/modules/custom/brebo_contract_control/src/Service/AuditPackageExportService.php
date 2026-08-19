<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Converts a frozen audit package into a human-readable export structure. */
final class AuditPackageExportService {

  public function __construct(
    private readonly Connection $database,
    private readonly AuditPackageVerificationService $verification,
  ) {}

  /** @return array<string, mixed> */
  public function export(int $packageId): array {
    $integrity = $this->verification->verify($packageId);
    if (($integrity['integrity_status'] ?? '') !== 'verified') {
      return ['exportable' => FALSE, 'status' => 'blocked_integrity_failure', 'integrity' => $integrity];
    }

    $package = $this->database->select('brebo_audit_package', 'p')->fields('p')->condition('id', $packageId)->execute()->fetchAssoc();
    $manifest = json_decode((string) $package['manifest_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    $readiness = (array) ($manifest['readiness'] ?? []);

    $sections = [
      ['title' => 'Samenvatting', 'data' => [
        'package_ref' => $package['package_ref'],
        'scope' => $package['scope'],
        'readiness_pct' => $package['readiness_pct'],
        'generated_at' => $package['generated_at'],
        'package_hash' => $package['package_hash'],
      ]],
      ['title' => 'Audit readiness', 'data' => $readiness],
      ['title' => 'Geldende beleidsversies', 'data' => (array) ($manifest['policy_versions'] ?? [])],
      ['title' => 'Bewijsregister', 'data' => (array) ($manifest['evidence_register'] ?? [])],
      ['title' => 'Uitzonderingen', 'data' => (array) ($manifest['exceptions'] ?? [])],
      ['title' => 'Integriteitscontrole', 'data' => $integrity],
    ];

    $gaps = [];
    foreach ((array) ($readiness['checks'] ?? []) as $check) {
      if (!($check['ready'] ?? FALSE)) {
        $gaps[] = $check;
      }
    }

    return [
      'exportable' => TRUE,
      'status' => 'ready_for_document_rendering',
      'package_ref' => $package['package_ref'],
      'title' => 'BREBO Auditpakket ' . $package['package_ref'],
      'sections' => $sections,
      'findings' => $gaps,
      'integrity' => $integrity,
      'render_targets' => ['pdf', 'docx'],
    ];
  }
}
