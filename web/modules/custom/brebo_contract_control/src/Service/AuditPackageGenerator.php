<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\AuditPackageGeneratorRepositoryInterface;

/** Builds a reproducible audit manifest from policies, evidence and exceptions. */
final class AuditPackageGenerator {

  public function __construct(
    private readonly AuditPackageGeneratorRepositoryInterface $repository,
    private readonly AuditReadinessEngine $readiness,
  ) {}

  /** @return array<string, mixed> */
  public function generate(string $scope, int $generatedBy, ?int $now = NULL): array {
    $now ??= time();
    $assessment = $this->readiness->assess($scope, $now);
    if (($assessment['status'] ?? '') === 'not_ready') {
      return ['generated' => FALSE, 'status' => 'blocked_not_ready', 'readiness' => $assessment];
    }

    $policies = $this->repository->findActivePolicies($scope, $now);
    $evidence = $this->repository->findEvidence($scope);

    $policyIds = array_values(array_map(static fn(array $p): int => (int) $p['id'], $policies));
    $exceptions = $this->repository->findExceptions($policyIds);

    $manifest = [
      'scope' => $scope,
      'generated_at' => $now,
      'generated_by' => $generatedBy,
      'readiness' => $assessment,
      'policy_versions' => array_map(static fn(array $p): array => [
        'id' => (int) $p['id'], 'code' => $p['policy_code'], 'version' => $p['version'], 'title' => $p['title'], 'effective_from' => (int) $p['effective_from'],
      ], $policies),
      'evidence_register' => array_map(static fn(array $e): array => [
        'id' => (int) $e['id'], 'policy_code' => $e['policy_code'], 'policy_version' => $e['policy_version'], 'result' => $e['result'], 'evidence_hash' => $e['evidence_hash'], 'evaluated_at' => (int) $e['evaluated_at'],
      ], $evidence),
      'exceptions' => $exceptions,
    ];
    $encoded = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $packageHash = hash('sha256', $encoded);
    $packageRef = 'AUD-' . gmdate('Ymd-His', $now) . '-' . substr($packageHash, 0, 10);

    $id = $this->repository->insertPackage([
      'package_ref' => $packageRef,
      'scope' => $scope,
      'readiness_pct' => (string) ($assessment['readiness_pct'] ?? 0),
      'status' => 'frozen',
      'manifest_json' => $encoded,
      'package_hash' => $packageHash,
      'generated_by' => $generatedBy,
      'generated_at' => $now,
    ]);

    return ['generated' => TRUE, 'package_id' => $id, 'package_ref' => $packageRef, 'package_hash' => $packageHash, 'manifest' => $manifest];
  }

}
