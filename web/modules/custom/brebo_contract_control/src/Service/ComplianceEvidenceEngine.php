<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Records immutable-style evidence snapshots for policy compliance decisions. */
final class ComplianceEvidenceEngine {

  public function __construct(
    private readonly Connection $database,
    private readonly PolicyEnforcementService $policyEnforcement,
  ) {}

  /** @param array<string, mixed> $context
   *  @param array<int, array<string, mixed>> $evidence
   *  @return array<string, mixed>
   */
  public function evaluateAndRecord(string $policyCode, string $scope, array $context, array $evidence, int $actorUid, ?int $now = NULL): array {
    $now ??= time();
    $decision = $this->policyEnforcement->evaluate($policyCode, $scope, $context, $now);
    $evidenceRefs = array_values(array_filter(array_map(static fn(array $item): string => trim((string) ($item['ref'] ?? '')), $evidence)));
    $result = (string) ($decision['status'] ?? 'unknown');

    if ($result === 'compliant' && $evidenceRefs === []) {
      $result = 'blocked_missing_evidence';
      $decision['status'] = $result;
      $decision['message'] = 'Compliance kan niet aantoonbaar worden vastgesteld zonder bewijsreferentie.';
    }

    $id = (int) $this->database->insert('brebo_compliance_evidence')->fields([
      'policy_code' => $policyCode,
      'policy_version' => (string) ($decision['policy_version'] ?? ''),
      'scope' => $scope,
      'result' => $result,
      'actor_uid' => $actorUid,
      'context_json' => json_encode($context, JSON_THROW_ON_ERROR),
      'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
      'decision_json' => json_encode($decision, JSON_THROW_ON_ERROR),
      'evidence_hash' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
      'evaluated_at' => $now,
    ])->execute();

    return ['evidence_record_id' => $id, 'decision' => $decision, 'evidence_count' => count($evidenceRefs)];
  }

  /** @return array<int, array<string, mixed>> */
  public function auditTrail(string $policyCode, ?string $scope = NULL): array {
    $query = $this->database->select('brebo_compliance_evidence', 'e')->fields('e')->condition('policy_code', $policyCode)->orderBy('evaluated_at', 'DESC');
    if ($scope !== NULL) {
      $query->condition('scope', $scope);
    }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
