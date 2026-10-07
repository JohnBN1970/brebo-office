<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\ManagementDecisionRecordRepositoryInterface;

/** Immutable-style registry for management decisions and later outcomes. */
final class ManagementDecisionRecordService {
  public function __construct(private readonly ManagementDecisionRecordRepositoryInterface $repository) {}

  public function ensureStorage(): void {
    $this->repository->ensureStorage();
  }

  public function record(string $decision, array $recommendation, int $uid, ?int $actionId = NULL, ?string $reason = NULL, ?int $now = NULL): int {
    $this->ensureStorage(); $now ??= time(); $scenario = (array) ($recommendation['recommended_scenario'] ?? []);
    $payload = ['decision' => $decision, 'recommendation' => $recommendation['recommendation'] ?? '', 'recommended_scenario' => $scenario, 'ranking' => $recommendation['ranking'] ?? [], 'confidence' => $recommendation['confidence'] ?? 'low', 'governance' => $recommendation['governance'] ?? '', 'reason' => $reason];
    $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    return $this->repository->insert(['decision' => $decision, 'scenario_key' => (string) ($scenario['key'] ?? ''), 'action_id' => $actionId, 'decided_by' => $uid, 'decision_json' => $encoded, 'decision_hash' => hash('sha256', $encoded), 'decided_at' => $now, 'review_30_at' => $now + 30 * 86400, 'review_90_at' => $now + 90 * 86400, 'status' => 'awaiting_outcome']);
  }

  /** @return array<int, array<string, mixed>> */
  public function dueReviews(?int $now = NULL): array {
    $this->ensureStorage(); $now ??= time();
    $rows = $this->repository->findUnmeasured();
    $due = [];
    foreach ($rows as $row) {
      if (empty($row['reviewed_30_at']) && (int) $row['review_30_at'] <= $now) { $row['review_days'] = 30; $due[] = $row; continue; }
      if (!empty($row['reviewed_30_at']) && empty($row['reviewed_90_at']) && (int) $row['review_90_at'] <= $now) { $row['review_days'] = 90; $due[] = $row; }
    }
    return $due;
  }

  public function recordOutcome(int $recordId, int $reviewDays, array $outcome, int $uid, ?int $now = NULL): void {
    $this->ensureStorage(); if (!in_array($reviewDays, [30, 90], TRUE)) { throw new \InvalidArgumentException('Alleen 30- en 90-dagenreviews zijn toegestaan.'); }
    $now ??= time(); $encoded = json_encode(['review_days' => $reviewDays, 'outcome' => $outcome, 'recorded_by' => $uid, 'recorded_at' => $now], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $fields = $reviewDays === 30 ? ['outcome_30_json' => $encoded, 'reviewed_30_at' => $now, 'status' => 'reviewed_30'] : ['outcome_90_json' => $encoded, 'reviewed_90_at' => $now, 'status' => 'measured'];
    $this->repository->update($recordId, $fields);
  }
}
