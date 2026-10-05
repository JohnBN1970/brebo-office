<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\LabourProductivityRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for labour productivity. */
final class DatabaseLabourProductivityRepository implements LabourProductivityRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function budgetLine(int $budgetLineId): ?array {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $query->fields('l');
    $query->addField('b', 'project_nid');
    $query->addField('b', 'status', 'budget_status');
    $row = $query->condition('l.id', $budgetLineId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateBudgetLine(int $budgetLineId, array $fields): void {
    $this->database->update('brebo_finance_budget_line')->fields($fields)->condition('id', $budgetLineId)->execute();
  }

  public function labourEntryBySource(string $sourceSystem, string $sourceRecordId): ?array {
    $row = $this->database->select('brebo_finance_labour_entry', 'e')
      ->fields('e')
      ->condition('source_system', $sourceSystem)
      ->condition('source_record_id', $sourceRecordId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function createLabourEntry(array $fields): int {
    return (int) $this->database->insert('brebo_finance_labour_entry')->fields($fields)->execute();
  }

  public function updateLabourEntry(int $entryId, array $fields): void {
    $this->database->update('brebo_finance_labour_entry')->fields($fields)->condition('id', $entryId)->execute();
  }

  public function labourEntry(int $entryId): ?array {
    $row = $this->database->select('brebo_finance_labour_entry', 'e')->fields('e')->condition('id', $entryId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function lockedLabourLines(int $projectNid): array {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $query->fields('l', ['id', 'work_package', 'description', 'budget_hours', 'hourly_cost_ex_vat', 'amount_ex_vat']);
    $query->condition('b.project_nid', $projectNid);
    $query->condition('b.budget_type', 'working');
    $query->condition('b.status', 'locked');
    $query->condition('l.cost_code', 'arbeid');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function inzetActualStatuses(int $projectNid): array {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->fields('e', ['assignment_nid', 'status', 'actual_hours', 'changed']);
    $query->condition('project_nid', $projectNid);
    $query->condition('source_system', 'brebo_inzet_actual');
    $query->condition('status', ['worked', 'approved'], 'IN');
    $query->isNotNull('assignment_nid');
    $result = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $result[(int) $row['assignment_nid']] = [
        'status' => (string) $row['status'],
        'actual_hours' => (string) $row['actual_hours'],
        'changed' => (int) $row['changed'],
      ];
    }
    return $result;
  }

  public function inzetActualStatus(int $projectNid, int $assignmentNid): ?array {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->fields('e', ['status', 'actual_hours', 'changed']);
    $query->condition('project_nid', $projectNid);
    $query->condition('source_system', 'brebo_inzet_actual');
    $query->condition('source_record_id', 'assignment:' . $assignmentNid);
    $row = $query->execute()->fetchAssoc();
    return $row === FALSE ? NULL : [
      'status' => (string) $row['status'],
      'actual_hours' => (string) $row['actual_hours'],
      'changed' => (int) $row['changed'],
    ];
  }

  public function sumHours(int $budgetLineId, string $field, array $statuses): string {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->condition('budget_line_id', $budgetLineId);
    $query->condition('status', $statuses, 'IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  public function sumCost(int $budgetLineId, array $statuses, string $sourceSystem): string {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->condition('budget_line_id', $budgetLineId);
    $query->condition('status', $statuses, 'IN');
    $query->condition('source_system', $sourceSystem);
    $query->addExpression('COALESCE(SUM(actual_cost_ex_vat), 0)', 'total');
    return (string) $query->execute()->fetchField();
  }

  public function maxProgress(int $budgetLineId, array $statuses): ?string {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->condition('budget_line_id', $budgetLineId);
    $query->condition('status', $statuses, 'IN');
    $query->addExpression('MAX(progress_pct)', 'progress');
    $value = $query->execute()->fetchField();
    return $value !== FALSE && $value !== NULL ? (string) $value : NULL;
  }

  public function countUnlinked(int $projectNid): int {
    return (int) $this->database->select('brebo_finance_labour_entry', 'e')
      ->condition('project_nid', $projectNid)
      ->isNull('building_object_id')
      ->condition('status', 'cancelled', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function appendAudit(array $audit): void {
    $this->database->insert('brebo_finance_audit')->fields($audit)->execute();
  }

}
