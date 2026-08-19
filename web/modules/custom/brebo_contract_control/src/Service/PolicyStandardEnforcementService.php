<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Enforces approved organizational lessons as versioned operational policy rules. */
final class PolicyStandardEnforcementService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function evaluate(string $policyCode, array $context, ?int $now = NULL): array {
    $now ??= time();
    $query = $this->database->select('brebo_policy_rule', 'p')->fields('p')
      ->condition('policy_code', $policyCode)
      ->condition('status', 'active')
      ->condition('effective_from', $now, '<=')
      ->orderBy('version', 'DESC')
      ->range(0, 1);
    $rule = $query->execute()->fetchAssoc();
    if (!$rule) {
      return ['allowed' => TRUE, 'status' => 'no_active_rule', 'policy_code' => $policyCode];
    }

    $requirements = json_decode((string) $rule['requirements_json'], TRUE) ?: [];
    $failed = [];
    foreach ($requirements as $key => $expected) {
      $actual = $context[$key] ?? NULL;
      if (is_bool($expected)) {
        if ((bool) $actual !== $expected) { $failed[] = $key; }
      }
      elseif (is_array($expected)) {
        if (!in_array($actual, $expected, TRUE)) { $failed[] = $key; }
      }
      elseif ($actual !== $expected) {
        $failed[] = $key;
      }
    }

    return [
      'allowed' => $failed === [],
      'status' => $failed === [] ? 'compliant' : 'blocked_non_compliant',
      'policy_code' => $policyCode,
      'version' => (string) $rule['version'],
      'failed_requirements' => $failed,
      'exception_allowed' => (bool) $rule['exception_allowed'],
      'message' => $failed === []
        ? 'Proces voldoet aan de actieve BREBO-standaard.'
        : 'Proces geblokkeerd: niet voldaan aan de actieve BREBO-standaard.',
    ];
  }

  /** @return array<string, mixed> */
  public function grantException(int $policyId, string $reason, int $requestedBy, int $approvedBy, ?int $validUntil = NULL, ?int $now = NULL): array {
    $now ??= time();
    $policy = $this->database->select('brebo_policy_rule', 'p')->fields('p')->condition('id', $policyId)->execute()->fetchAssoc();
    if (!$policy) { throw new \InvalidArgumentException('Onbekende policyregel.'); }
    if (!(bool) $policy['exception_allowed']) { throw new \LogicException('Voor deze policy zijn uitzonderingen niet toegestaan.'); }
    if ($requestedBy === $approvedBy) { throw new \LogicException('Vier-ogenprincipe: aanvrager mag eigen policy-uitzondering niet goedkeuren.'); }
    if (trim($reason) === '') { throw new \InvalidArgumentException('Motivatie voor de uitzondering is verplicht.'); }

    $id = (int) $this->database->insert('brebo_policy_exception')->fields([
      'policy_id' => $policyId,
      'reason' => $reason,
      'requested_by' => $requestedBy,
      'approved_by' => $approvedBy,
      'approved_at' => $now,
      'valid_until' => $validUntil,
      'status' => 'approved',
    ])->execute();

    return ['exception_id' => $id, 'status' => 'approved', 'valid_until' => $validUntil];
  }
}
