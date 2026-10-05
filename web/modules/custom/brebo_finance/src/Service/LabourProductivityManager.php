<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\LabourProductivityRepositoryInterface;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls labour budgets, planning imports and productivity forecasts.
 */
final class LabourProductivityManager {

  private const array ACTIVE_PLANNING_STATUSES = ['planned', 'confirmed', 'worked', 'approved'];
  private const array ACTUAL_SUBMITTED_STATUSES = ['worked', 'approved'];

  public function __construct(
    private readonly LabourProductivityRepositoryInterface $repository,
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
    $this->repository->updateBudgetLine($budgetLineId, [
        'budget_hours' => $budgetHours,
        'hourly_cost_ex_vat' => $hourlyCostExVat,
        'amount_ex_vat' => $this->decimal->multiply($budgetHours, $hourlyCostExVat),
        'amount_inc_vat' => $this->decimal->multiply($budgetHours, $hourlyCostExVat),
        'changed' => $now,
        'changed_by' => $userId,
      ]);

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
    $existing = $this->repository->labourEntryBySource(trim($sourceSystem), trim($sourceRecordId));
    if ($existing !== NULL && (int) $existing['recorded_at'] > $recordedAt) {
      throw new UnexpectedValueException('An older labour source record cannot overwrite newer evidence.');
    }
    if ($existing !== NULL && hash_equals((string) $existing['source_hash'], $sourceHash)) {
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

    if ($existing === NULL) {
      $entryId = $this->repository->createLabourEntry($fields + [
        'source_system' => trim($sourceSystem),
        'source_record_id' => trim($sourceRecordId),
        'created' => $now,
        'created_by' => $systemUserId,
      ]);
    }
    else {
      $entryId = (int) $existing['id'];
      $this->repository->updateLabourEntry($entryId, $fields);
    }

    $this->audit(
      $projectNid,
      'labour_entry',
      $entryId,
      $existing === NULL ? 'source_created' : 'source_updated',
      $existing !== NULL ? $this->hash($existing) : NULL,
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
      'planned_cost_ex_vat' => '0.0000',
      'actual_submitted_cost_ex_vat' => '0.0000',
      'actual_approved_cost_ex_vat' => '0.0000',
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
      $plannedCost = $this->sumCost($lineId, ['planned', 'confirmed'], 'brebo_inzet');
      $submittedCost = $this->sumCost($lineId, ['worked'], 'brebo_inzet_actual');
      $approvedCost = $this->sumCost($lineId, ['approved'], 'brebo_inzet_actual');
      $forecastCost = $this->maximum([$plannedCost, $submittedCost, $approvedCost]);
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
        'planned_cost_ex_vat' => $plannedCost,
        'actual_submitted_cost_ex_vat' => $submittedCost,
        'actual_approved_cost_ex_vat' => $approvedCost,
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
  /**
   * Returns the authoritative locked labour budget lines for a project.
   *
   * @return list<array<string, mixed>>
   */
  public function labourBudgetLines(int $projectNid): array {
    return $this->repository->lockedLabourLines($projectNid);
  }

  /**
   * Returns current Inzet actual-review status keyed by assignment node ID.
   *
   * @return array<int, array{status:string,actual_hours:string,changed:int}>
   */
  public function inzetActualStatuses(int $projectNid): array {
    return $this->repository->inzetActualStatuses($projectNid);
  }

  /**
   * Returns the current submitted/approved Inzet evidence for one assignment.
   *
   * @return array{status:string,actual_hours:string,changed:int}|null
   */
  public function inzetActualStatus(int $projectNid, int $assignmentNid): ?array {
    if ($projectNid <= 0 || $assignmentNid <= 0) {
      return NULL;
    }
    return $this->repository->inzetActualStatus($projectNid, $assignmentNid);
  }

  private function lockedLabourLines(int $projectNid): array {
    return $this->repository->lockedLabourLines($projectNid);
  }

  /**
   * @param list<string> $statuses
   */
  private function sumHours(int $budgetLineId, string $field, array $statuses): string {
    return $this->repository->sumHours($budgetLineId, $field, $statuses);
  }

  /**
   * @param list<string> $statuses
   */
  private function sumCost(int $budgetLineId, array $statuses, string $sourceSystem): string {
    return $this->repository->sumCost($budgetLineId, $statuses, $sourceSystem);
  }

  private function maxProgress(int $budgetLineId): ?string {
    return $this->repository->maxProgress($budgetLineId, self::ACTIVE_PLANNING_STATUSES);
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
    return $this->repository->countUnlinked($projectNid);
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
    $line = $this->repository->budgetLine($budgetLineId);
    if ($line === NULL) {
      throw new UnexpectedValueException('Working-budget line does not exist.');
    }
    return $line;
  }

  /**
   * @return array<string, mixed>
   */
  private function loadEntry(int $entryId): array {
    $entry = $this->repository->labourEntry($entryId);
    if ($entry === NULL) {
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
    $this->repository->appendAudit([
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
      ]);
  }

}
