<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialPhaseGateRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for Finance phase-gate persistence. */
final class DatabaseFinancialPhaseGateRepository implements FinancialPhaseGateRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_finance_phase_gate_exception')) {
      return;
    }
    $schema->createTable('brebo_finance_phase_gate_exception', [
      'description' => 'Temporary four-eyes exceptions for deterministic financial phase gates.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'gate' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'requested'],
        'finding_ids' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'reason' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'control_measure' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'expires_at' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'evidence' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'approval_note' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'content_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'requested_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'approved_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'project_gate_status' => ['project_nid', 'gate', 'status'],
        'expires_at' => ['expires_at'],
        'content_hash' => ['content_hash'],
      ],
    ]);
  }

  public function blockingFindings(int $projectId, array $highControlCodes): array {
    $query = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f')
      ->condition('project_nid', $projectId)
      ->condition('status', ['resolved_verified', 'resolved_automatically'], 'NOT IN');
    $or = $query->orConditionGroup()->condition('severity', 'critical');
    $or->condition($query->andConditionGroup()->condition('severity', 'high')->condition('control_code', $highControlCodes, 'IN'));
    $query->condition($or);
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function activeException(int $projectId, string $gate, int $now): ?array {
    $row = $this->database->select('brebo_finance_phase_gate_exception', 'e')
      ->fields('e')
      ->condition('project_nid', $projectId)
      ->condition('gate', $gate)
      ->condition('status', 'approved')
      ->condition('expires_at', $now, '>')
      ->orderBy('changed', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function createException(array $fields): int {
    return (int) $this->database->insert('brebo_finance_phase_gate_exception')->fields($fields)->execute();
  }

  public function exception(int $exceptionId): ?array {
    $row = $this->database->select('brebo_finance_phase_gate_exception', 'e')
      ->fields('e')
      ->condition('id', $exceptionId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateException(int $exceptionId, array $fields): void {
    $this->database->update('brebo_finance_phase_gate_exception')
      ->fields($fields)
      ->condition('id', $exceptionId)
      ->execute();
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
