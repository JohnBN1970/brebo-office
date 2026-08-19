<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Immutable-style registry for management decisions and later outcomes. */
final class ManagementDecisionRecordService {
  public function __construct(private readonly Connection $database) {}

  /** @return int */
  public function record(string $decision, array $recommendation, int $uid, ?int $actionId = NULL, ?string $reason = NULL, ?int $now = NULL): int {
    $now ??= time();
    $scenario = (array) ($recommendation['recommended_scenario'] ?? []);
    $payload = [
      'decision' => $decision,
      'recommendation' => $recommendation['recommendation'] ?? '',
      'recommended_scenario' => $scenario,
      'ranking' => $recommendation['ranking'] ?? [],
      'confidence' => $recommendation['confidence'] ?? 'low',
      'governance' => $recommendation['governance'] ?? '',
      'reason' => $reason,
    ];
    $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    return (int) $this->database->insert('brebo_management_decision_record')->fields([
      'decision' => $decision,
      'scenario_key' => (string) ($scenario['key'] ?? ''),
      'action_id' => $actionId,
      'decided_by' => $uid,
      'decision_json' => $encoded,
      'decision_hash' => hash('sha256', $encoded),
      'decided_at' => $now,
      'review_30_at' => $now + 30 * 86400,
      'review_90_at' => $now + 90 * 86400,
      'status' => 'awaiting_outcome',
    ])->execute();
  }

  public function recordOutcome(int $recordId, int $reviewDays, array $outcome, int $uid, ?int $now = NULL): void {
    if (!in_array($reviewDays, [30, 90], TRUE)) { throw new \InvalidArgumentException('Alleen 30- en 90-dagenreviews zijn toegestaan.'); }
    $now ??= time();
    $encoded = json_encode(['review_days' => $reviewDays, 'outcome' => $outcome, 'recorded_by' => $uid, 'recorded_at' => $now], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $fields = $reviewDays === 30 ? ['outcome_30_json' => $encoded, 'reviewed_30_at' => $now] : ['outcome_90_json' => $encoded, 'reviewed_90_at' => $now, 'status' => 'measured'];
    $this->database->update('brebo_management_decision_record')->fields($fields)->condition('id', $recordId)->execute();
  }
}
