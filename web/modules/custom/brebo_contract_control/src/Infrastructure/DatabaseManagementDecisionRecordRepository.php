<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ManagementDecisionRecordRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for management decision records. */
final class DatabaseManagementDecisionRecordRepository implements ManagementDecisionRecordRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_management_decision_record')) {
      return;
    }

    $schema->createTable('brebo_management_decision_record', [
      'description' => 'BREBO management decision records with 30/90 day outcome reviews.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'decision' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'scenario_key' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'action_id' => ['type' => 'int', 'not null' => FALSE],
        'decided_by' => ['type' => 'int', 'not null' => TRUE],
        'decision_json' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'decision_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'decided_at' => ['type' => 'int', 'not null' => TRUE],
        'review_30_at' => ['type' => 'int', 'not null' => TRUE],
        'review_90_at' => ['type' => 'int', 'not null' => TRUE],
        'outcome_30_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'reviewed_30_at' => ['type' => 'int', 'not null' => FALSE],
        'outcome_90_json' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'reviewed_90_at' => ['type' => 'int', 'not null' => FALSE],
        'status' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'awaiting_outcome'],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'status' => ['status'],
        'review_30_at' => ['review_30_at'],
        'review_90_at' => ['review_90_at'],
        'action_id' => ['action_id'],
      ],
    ]);
  }

  /** @param array<string, mixed> $record */
  public function insert(array $record): int {
    return (int) $this->database
      ->insert('brebo_management_decision_record')
      ->fields($record)
      ->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function findUnmeasured(): array {
    return $this->database
      ->select('brebo_management_decision_record', 'd')
      ->fields('d')
      ->condition('status', 'measured', '<>')
      ->orderBy('decided_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @param array<string, mixed> $fields */
  public function update(int $recordId, array $fields): void {
    $this->database
      ->update('brebo_management_decision_record')
      ->fields($fields)
      ->condition('id', $recordId)
      ->execute();
  }

}
