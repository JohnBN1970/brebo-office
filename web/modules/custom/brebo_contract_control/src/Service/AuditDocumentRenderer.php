<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

/** Converts a verified audit export into a BREBO report document model. */
final class AuditDocumentRenderer {

  public function __construct(private readonly AuditPackageExportService $exporter) {}

  /** @return array<string, mixed> */
  public function render(int $packageId): array {
    $export = $this->exporter->export($packageId);
    if (!($export['exportable'] ?? FALSE)) {
      return [
        'renderable' => FALSE,
        'status' => $export['status'] ?? 'blocked',
        'verification' => $export['verification'] ?? [],
      ];
    }

    $package = (array) ($export['package'] ?? []);
    $readiness = (array) ($package['readiness'] ?? []);
    $policies = (array) ($package['policy_versions'] ?? []);
    $evidence = (array) ($package['evidence_register'] ?? []);
    $exceptions = (array) ($package['exceptions'] ?? []);
    $checks = (array) ($readiness['checks'] ?? []);
    $openFindings = array_values(array_filter($checks, static fn(array $check): bool => !($check['ready'] ?? FALSE)));

    $sections = [
      [
        'key' => 'cover',
        'title' => 'BREBO Auditrapport',
        'type' => 'cover',
        'data' => [
          'scope' => $package['scope'] ?? '',
          'package_ref' => $export['package_ref'] ?? '',
          'generated_at' => $package['generated_at'] ?? NULL,
          'package_hash' => $export['package_hash'] ?? '',
        ],
      ],
      [
        'key' => 'management_summary',
        'title' => 'Managementsamenvatting',
        'type' => 'summary',
        'data' => [
          'readiness_pct' => $readiness['readiness_pct'] ?? 0,
          'readiness_status' => $readiness['status'] ?? 'unknown',
          'required_controls' => $readiness['required_controls'] ?? count($policies),
          'audit_ready_controls' => $readiness['audit_ready_controls'] ?? 0,
          'open_findings' => count($openFindings),
          'exceptions' => count($exceptions),
          'integrity_status' => 'verified',
        ],
      ],
      ['key' => 'findings', 'title' => 'Openstaande bevindingen', 'type' => 'findings', 'data' => $openFindings],
      ['key' => 'policy_register', 'title' => 'Geldende beleidsregels', 'type' => 'register', 'data' => $policies],
      ['key' => 'evidence_index', 'title' => 'Bewijsindex', 'type' => 'register', 'data' => $evidence],
      ['key' => 'exceptions', 'title' => 'Uitzonderingen en goedkeuringen', 'type' => 'register', 'data' => $exceptions],
      [
        'key' => 'verification',
        'title' => 'Integriteitsverificatie',
        'type' => 'verification',
        'data' => [
          'status' => 'verified',
          'package_hash' => $export['package_hash'] ?? '',
          'verification' => $export['verification'] ?? [],
        ],
      ],
    ];

    return [
      'renderable' => TRUE,
      'document_type' => 'brebo_audit_report',
      'title' => 'BREBO Auditrapport - ' . (string) ($package['scope'] ?? ''),
      'package_ref' => $export['package_ref'] ?? '',
      'table_of_contents' => array_map(static fn(array $section): array => ['key' => $section['key'], 'title' => $section['title']], $sections),
      'sections' => $sections,
      'output_targets' => ['pdf', 'docx'],
      'governance' => 'Het rapport is een weergave van een bevroren en geverifieerd auditpakket. Wijzigingen vereisen generatie van een nieuw auditpakket.',
    ];
  }
}
