<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Immutable-style registry for management decisions and later outcomes. */
final class ManagementDecisionRecordService {
  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_management_decision_record')) { return; }
    $schema->createTable('brebo_management_decision_record', [
      'description' => 'BREBO management decision records with 30/90 day outcome reviews.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE], 'decision' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE], 'scenario_key' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => ''], 'action_id' => ['type' => 'int', 'not null' => FALSE], 'decided_by' => ['type' => 'int', 'not null' => TRUE], 'decision_json' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE], 'decision_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE], 'decided_at' => ['type' => 'int', 'not null' => TRUE], 'review_30_at' => ['type' => 'int', 'not null' => TRUE], 'review_90_at' => ['type' => 'int', 'not null' => TRUE], 'outcome_30_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE], 'reviewed_30_at' => ['type' => 'int', 'not null' => FALSE], 'outcome_90_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE], 'reviewed_90_at' => ['type' => 'int', 'not null' => FALSE], 'status' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'awaiting_outcome'],
      ],
      'primary key' => ['id'], 'indexes' => ['status' => ['status'], 'review_30_at' => ['review_30_at'], 'review_90_at' => ['review_90_at'], 'action_id' => ['action_id']],
    ]);
  }

  public function record(string $decision, array $recommendation, int $uid, ?int $actionId = NULL, ?string $reason = NULL, ?int $now = NULL): int {
    $this->ensureStorage(); $now ??= time(); $scenario = (array) ($recommendation['recommended_scenario'] ?? []);
    $payload = ['decision' => $decision, 'recommendation' => $recommendation['recommendation'] ?? '', 'recommended_scenario' => $scenario, 'ranking' => $recommendation['ranking'] ?? [], 'confidence' => $recommendation['confidence'] ?? 'low', 'governance' => $recommendation['governance'] ?? '', 'reason' => $reason];
    $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    return (int) $this->database->insert('brebo_management_decision_record')->fields(['decision' => $decision, 'scenario_key' => (string) ($scenario['key'] ?? ''), 'action_id' => $actionId, 'decided_by' => $uid, 'decision_json' => $encoded, 'decision_hash' => hash('sha256', $encoded), 'decided_at' => $now, 'review_30_at' => $now + 30 * 86400, 'review_90_at' => $now + 90 * 86400, 'status' => 'awaiting_outcome'])->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function dueReviews(?int $now = NULL): array {
    $this->ensureStorage(); $now ??= time();
    $rows = $this->database->select('brebo_management_decision_record', 'd')->fields('d')->condition('status', 'measured', '<>')->orderBy('decided_at', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
    $this->database->update('brebo_management_decision_record')->fields($fields)->condition('id', $recordId)->execute();
  }
}
