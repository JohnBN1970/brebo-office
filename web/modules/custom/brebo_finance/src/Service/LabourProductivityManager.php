<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls labour budgets, planning imports and productivity forecasts.
 */
final class LabourProductivityManager {

  private const array ACTIVE_PLANNING_STATUSES = ['planned', 'confirmed', 'worked', 'approved'];
  private const array ACTUAL_SUBMITTED_STATUSES = ['worked', 'approved'];

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Sets executable labour assumptions while the working budget is editable.
   */
  public function configureBudgetLine(
    int $budgetLineId,
    string $budgetHours,
    string $hourlyCostExVat,
    string $reason,
    int $userId,
  ): void {
    $this->assertNonNegative($budgetHours, 'Budget hours');
    $this->assertNonNegative($hourlyCostExVat, 'Hourly cost');
    if ($this->decimal->compare($budgetHours, '0') === 0
      || $this->decimal->compare($hourlyCostExVat, '0') === 0
    ) {
      throw new InvalidArgumentException('Labour hours and hourly cost must be greater than zero.');
    }
    if (trim($reason) === '' || $userId <= 0) {
      throw new InvalidArgumentException('Labour budget configuration requires a reason and user.');
    }

    $line = $this->loadBudgetLine($budgetLineId);
    if ($line['cost_code'] !== 'arbeid' || !in_array($line['budget_status'], ['draft', 'in_review', 'rejected'], TRUE)) {
      throw new UnexpectedValueException('Only an editable labour working-budget line can be configured.');
    }

    $beforeHash = $this->hash($line);
    $now = time();
    $this->database->update('brebo_finance_budget_line')
      ->fields([
        'budget_hours' => $budgetHours,
        'hourly_cost_ex_vat' => $hourlyCostExVat,
        'amount_ex_vat' => $this->decimal->multiply($budgetHours, $hourlyCostExVat),
        'amount_inc_vat' => $this->decimal->multiply($budgetHours, $hourlyCostExVat),
        'changed' => $now,
        'changed_by' => $userId,
      ])
      ->condition('id', $budgetLineId)
      ->execute();

    $this->audit(
      (int) $line['project_nid'],
      'budget_line',
      $budgetLineId,
      'labour_configured',
      $beforeHash,
      $this->hash($this->loadBudgetLine($budgetLineId)),
      [
        'budget_hours' => $budgetHours,
        'hourly_cost_ex_vat' => $hourlyCostExVat,
      ],
      trim($reason),
      $userId,
      $now,
    );
  }

  /**
   * Imports or updates one external personnel assignment idempotently.
   *
   * @param array<string, mixed> $sourcePayload
   */
  public function synchronizeEntry(
    int $projectNid,
    int $budgetLineId,
    string $sourceSystem,
    string $sourceRecordId,
    ?string $sourceVersion,
    ?int $assignmentNid,
    ?int $activityNid,
    ?string $buildingObjectType,
    ?int $buildingObjectId,
    ?string $resourceRef,
    string $plannedHours,
    string $actualHours,
    ?string $progressPct,
    string $actualCostExVat,
    string $status,
    int $recordedAt,
    array $sourcePayload,
    int $systemUserId = 0,
  ): int {
    foreach ([
      'Planned hours' => $plannedHours,
      'Actual hours' => $actualHours,
      'Actual cost' => $actualCostExVat,
    ] as $label => $value) {
      $this->assertNonNegative($value, $label);
    }
    if ($progressPct !== NULL
      && ($this->decimal->compare($progressPct, '0') < 0
        || $this->decimal->compare($progressPct, '100') > 0)
    ) {
      throw new InvalidArgumentException('Progress must be between zero and one hundred.');
    }
    if (!in_array($status, [...self::ACTIVE_PLANNING_STATUSES, 'cancelled'], TRUE)) {
      throw new InvalidArgumentException('Unknown labour-entry status.');
    }
    if (trim($sourceSystem) === '' || trim($sourceRecordId) === '' || $sourcePayload === [] || $recordedAt <= 0) {
      throw new InvalidArgumentException('Labour entry requires source identity, timestamp and evidence.');
    }

    $line = $this->loadBudgetLine($budgetLineId);
    if ((int) $line['project_nid'] !== $projectNid
      || $line['budget_status'] !== 'locked'
      || $line['cost_code'] !== 'arbeid'
    ) {
      throw new UnexpectedValueException('Personnel time must reference a locked labour budget line of the same project.');
    }

    $sourceJson = json_encode($sourcePayload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $sourceHash = hash('sha256', $sourceJson);
    $existing = $this->database->select('brebo_finance_labour_entry', 'e')
      ->fields('e')
      ->condition('source_system', trim($sourceSystem))
      ->condition('source_record_id', trim($sourceRecordId))
      ->execute()
      ->fetchAssoc();
    if ($existing !== FALSE && (int) $existing['recorded_at'] > $recordedAt) {
      throw new UnexpectedValueException('An older labour source record cannot overwrite newer evidence.');
    }
    if ($existing !== FALSE && hash_equals((string) $existing['source_hash'], $sourceHash)) {
      return (int) $existing['id'];
    }

    $now = time();
    $fields = [
      'project_nid' => $projectNid,
      'budget_line_id' => $budgetLineId,
      'source_version' => $sourceVersion !== NULL ? trim($sourceVersion) : NULL,
      'assignment_nid' => $assignmentNid,
      'activity_nid' => $activityNid,
      'building_object_type' => $buildingObjectType !== NULL ? trim($buildingObjectType) : NULL,
      'building_object_id' => $buildingObjectId,
      'resource_ref' => $resourceRef !== NULL ? trim($resourceRef) : NULL,
      'planned_hours' => $plannedHours,
      'actual_hours' => $actualHours,
      'progress_pct' => $progressPct,
      'actual_cost_ex_vat' => $actualCostExVat,
      'status' => $status,
      'source_hash' => $sourceHash,
      'recorded_at' => $recordedAt,
      'changed' => $now,
      'changed_by' => $systemUserId,
    ];

    if ($existing === FALSE) {
      $entryId = (int) $this->database->insert('brebo_finance_labour_entry')
        ->fields($fields + [
          'source_system' => trim($sourceSystem),
          'source_record_id' => trim($sourceRecordId),
          'created' => $now,
          'created_by' => $systemUserId,
        ])
        ->execute();
    }
    else {
      $entryId = (int) $existing['id'];
      $this->database->update('brebo_finance_labour_entry')
        ->fields($fields)
        ->condition('id', $entryId)
        ->execute();
    }

    $this->audit(
      $projectNid,
      'labour_entry',
      $entryId,
      $existing === FALSE ? 'source_created' : 'source_updated',
      $existing !== FALSE ? $this->hash($existing) : NULL,
      $this->hash($this->loadEntry($entryId)),
      ['source_system' => trim($sourceSystem), 'source_hash' => $sourceHash],
      'Synchronized from sealed personnel or time-registration evidence.',
      $systemUserId,
      $now,
    );
    return $entryId;
  }

  /**
   * @return array<string, mixed>
   */
  public function analyzeProject(int $projectNid): array {
    $lines = $this->lockedLabourLines($projectNid);
    $result = [];
    $totals = [
      'budget_hours' => '0.0000',
      'planned_hours' => '0.0000',
      'actual_submitted_hours' => '0.0000',
      'actual_approved_hours' => '0.0000',
      'forecast_end_hours' => '0.0000',
      'budget_cost_ex_vat' => '0.0000',
      'forecast_cost_ex_vat' => '0.0000',
      'forecast_variance_ex_vat' => '0.0000',
    ];

    foreach ($lines as $line) {
      $lineId = (int) $line['id'];
      $planned = $this->sumHours($lineId, 'planned_hours', self::ACTIVE_PLANNING_STATUSES);
      $submitted = $this->sumHours($lineId, 'actual_hours', self::ACTUAL_SUBMITTED_STATUSES);
      $approved = $this->sumHours($lineId, 'actual_hours', ['approved']);
      $progress = $this->maxProgress($lineId);
      $earnedForecast = ($progress !== NULL && $this->decimal->compare($progress, '0') > 0)
        ? $this->decimal->percentage($approved, $progress)
        : '0.0000';
      $forecastHours = $this->maximum([$planned, $submitted, $approved, $earnedForecast]);
      $forecastCost = $this->decimal->multiply($forecastHours, (string) $line['hourly_cost_ex_vat']);
      $variance = $this->decimal->subtract($forecastCost, (string) $line['amount_ex_vat']);

      $row = [
        'budget_line_id' => $lineId,
        'work_package' => $line['work_package'],
        'description' => $line['description'],
        'budget_hours' => (string) $line['budget_hours'],
        'planned_hours' => $planned,
        'actual_submitted_hours' => $submitted,
        'actual_approved_hours' => $approved,
        'progress_pct' => $progress,
        'forecast_end_hours' => $forecastHours,
        'remaining_budget_hours' => $this->decimal->subtract((string) $line['budget_hours'], $approved),
        'budget_cost_ex_vat' => (string) $line['amount_ex_vat'],
        'forecast_cost_ex_vat' => $forecastCost,
        'forecast_variance_ex_vat' => $variance,
        'status' => $this->lineStatus((string) $line['budget_hours'], $planned, $approved, $forecastHours),
      ];
      $result[] = $row;

      foreach (array_keys($totals) as $key) {
        $totals[$key] = $this->decimal->add($totals[$key], (string) $row[$key]);
      }
    }

    return [
      'project_nid' => $projectNid,
      'generated_at' => time(),
      'lines' => $result,
      'totals' => $totals,
      'unlinked_entries' => $this->countUnlinked($projectNid),
      'principle' => 'Only approved actual hours affect the financial actual; submitted hours remain visible as pending evidence.',
    ];
  }

  private function lineStatus(string $budget, string $planned, string $approved, string $forecast): string {
    if ($this->decimal->compare($approved, $budget) > 0 || $this->decimal->compare($forecast, $budget) > 0) {
      return 'forecast_overrun';
    }
    if ($this->decimal->compare($planned, $budget) > 0) {
      return 'planning_overrun';
    }
    if ($this->decimal->compare($planned, $budget) < 0) {
      return 'underallocated';
    }
    return 'in_control';
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function lockedLabourLines(int $projectNid): array {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $query->fields('l', ['id', 'work_package', 'description', 'budget_hours', 'hourly_cost_ex_vat', 'amount_ex_vat']);
    $query->condition('b.project_nid', $projectNid);
    $query->condition('b.budget_type', 'working');
    $query->condition('b.status', 'locked');
    $query->condition('l.cost_code', 'arbeid');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param list<string> $statuses
   */
  private function sumHours(int $budgetLineId, string $field, array $statuses): string {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->condition('budget_line_id', $budgetLineId);
    $query->condition('status', $statuses, 'IN');
    $query->addExpression("COALESCE(SUM($field), 0)", 'total');
    return (string) $query->execute()->fetchField();
  }

  private function maxProgress(int $budgetLineId): ?string {
    $query = $this->database->select('brebo_finance_labour_entry', 'e');
    $query->condition('budget_line_id', $budgetLineId);
    $query->condition('status', self::ACTIVE_PLANNING_STATUSES, 'IN');
    $query->addExpression('MAX(progress_pct)', 'progress');
    $value = $query->execute()->fetchField();
    return $value !== FALSE && $value !== NULL ? (string) $value : NULL;
  }

  /**
   * @param list<string> $values
   */
  private function maximum(array $values): string {
    $maximum = '0.0000';
    foreach ($values as $value) {
      if ($this->decimal->compare($value, $maximum) > 0) {
        $maximum = $value;
      }
    }
    return $maximum;
  }

  private function countUnlinked(int $projectNid): int {
    return (int) $this->database->select('brebo_finance_labour_entry', 'e')
      ->condition('project_nid', $projectNid)
      ->isNull('building_object_id')
      ->condition('status', 'cancelled', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  private function assertNonNegative(string $value, string $label): void {
    if ($this->decimal->compare($value, '0') < 0) {
      throw new InvalidArgumentException("$label may not be negative.");
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function loadBudgetLine(int $budgetLineId): array {
    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $query->fields('l');
    $query->addField('b', 'project_nid');
    $query->addField('b', 'status', 'budget_status');
    $line = $query->condition('l.id', $budgetLineId)->execute()->fetchAssoc();
    if ($line === FALSE) {
      throw new UnexpectedValueException('Working-budget line does not exist.');
    }
    return $line;
  }

  /**
   * @return array<string, mixed>
   */
  private function loadEntry(int $entryId): array {
    $entry = $this->database->select('brebo_finance_labour_entry', 'e')
      ->fields('e')
      ->condition('id', $entryId)
      ->execute()
      ->fetchAssoc();
    if ($entry === FALSE) {
      throw new UnexpectedValueException('Labour entry does not exist.');
    }
    return $entry;
  }

  /**
   * @param array<string, mixed> $record
   */
  private function hash(array $record): string {
    ksort($record);
    return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function audit(
    int $projectNid,
    string $entityType,
    int $entityId,
    string $action,
    ?string $beforeHash,
    string $afterHash,
    array $payload,
    string $reason,
    int $userId,
    int $now,
  ): void {
    $this->database->insert('brebo_finance_audit')
      ->fields([
        'project_nid' => $projectNid,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'action' => $action,
        'before_hash' => $beforeHash,
        'after_hash' => $afterHash,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'reason' => $reason,
        'created' => $now,
        'created_by' => $userId,
      ])
      ->execute();
  }

}
