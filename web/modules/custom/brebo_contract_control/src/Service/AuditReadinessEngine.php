<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\AuditReadinessReadRepositoryInterface;

/** Calculates evidence completeness and audit readiness per scope. */
final class AuditReadinessEngine {

  public function __construct(private readonly AuditReadinessReadRepositoryInterface $repository) {}

  /** @return array<string, mixed> */
  public function assess(string $scope, ?int $now = NULL): array {
    $now ??= time();
    $rules = $this->repository->activePolicyRules($scope, $now);

    $checks = [];
    $complete = 0;
    foreach ($rules as $rule) {
      $latest = $this->repository->latestComplianceEvidence(
        (string) $rule['policy_code'],
        (string) $rule['version'],
        $scope,
      );

      $ready = $latest && (string) ($latest['result'] ?? '') === 'compliant' && trim((string) ($latest['evidence_json'] ?? '')) !== '' && trim((string) ($latest['evidence_hash'] ?? '')) !== '';
      if ($ready) { $complete++; }
      $checks[] = [
        'policy_code' => $rule['policy_code'],
        'policy_version' => $rule['version'],
        'title' => $rule['title'],
        'ready' => (bool) $ready,
        'latest_result' => $latest['result'] ?? 'missing',
        'evidence_record_id' => $latest['id'] ?? NULL,
        'missing' => $ready ? [] : $this->missing($latest),
      ];
    }

    $total = count($rules);
    $pct = $total > 0 ? round(($complete / $total) * 100, 1) : 100.0;
    return [
      'scope' => $scope,
      'required_controls' => $total,
      'audit_ready_controls' => $complete,
      'readiness_pct' => $pct,
      'status' => $pct === 100.0 ? 'audit_ready' : ($pct >= 80 ? 'minor_gaps' : ($pct >= 50 ? 'material_gaps' : 'not_ready')),
      'checks' => $checks,
      'generated_at' => $now,
    ];
  }

  /** @param array<string, mixed>|null $latest
   *  @return string[]
   */
  private function missing(?array $latest): array {
    if (!$latest) { return ['compliance_assessment', 'evidence', 'evidence_hash']; }
    $missing = [];
    if (($latest['result'] ?? '') !== 'compliant') { $missing[] = 'compliant_result'; }
    if (trim((string) ($latest['evidence_json'] ?? '')) === '') { $missing[] = 'evidence'; }
    if (trim((string) ($latest['evidence_hash'] ?? '')) === '') { $missing[] = 'evidence_hash'; }
    return $missing;
  }
}
