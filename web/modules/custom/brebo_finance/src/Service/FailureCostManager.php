<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls financial learning from failure, recovery and prevention.
 */
final class FailureCostManager {

  private const array CATEGORIES = [
    'calculation',
    'procurement',
    'work_preparation',
    'execution',
    'supplier',
    'design_change',
    'resident_damage',
    'missing_information',
    'planning_logistics',
    'quality_failure',
    'safety',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * @param array<string, string> $costComponents
   * @param array<string, mixed> $evidence
   */
  public function record(
    int $projectNid,
    string $failureNumber,
    string $category,
    string $sourceType,
    int $sourceId,
    ?int $budgetLineId,
    ?string $buildingObjectType,
    ?int $buildingObjectId,
    ?string $responsiblePartyRef,
    string $title,
    string $cause,
    string $consequence,
    string $preventiveMeasure,
    array $costComponents,
    string $recoverableAmountExVat,
    int $ownerUid,
    string $dueDate,
    array $evidence,
    int $userId,
  ): int {
    if (!in_array($category, self::CATEGORIES, TRUE)) {
      throw new InvalidArgumentException('Unknown failure-cost category.');
    }
    foreach ([$failureNumber, $sourceType, $title, $cause, $consequence, $preventiveMeasure] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('Failure identity, cause, consequence and preventive measure are required.');
      }
    }
    if (($buildingObjectType === NULL) !== ($buildingObjectId === NULL)) {
      throw new InvalidArgumentException('Building-object type and identifier must be supplied together.');
    }
    if ($ownerUid <= 0 || $userId <= 0 || !$this->validDate($dueDate) || $evidence === []) {
      throw new InvalidArgumentException('Failure cost requires owner, valid deadline, evidence and recorder.');
    }

    $normalized = [];
    foreach ([
      'labour',
      'material',
      'equipment',
      'subcontracting',
      'other',
    ] as $component) {
      $value = $costComponents[$component] ?? '0';
      $this->assertNonNegative($value, ucfirst($component) . ' cost');
      $normalized[$component] = $value;
    }
    $this->assertNonNegative($recoverableAmountExVat, 'Recoverable amount');
    $total = '0.0000';
    foreach ($normalized as $value) {
      $total = $this->decimal->add($total, $value);
    }
    if ($this->decimal->compare($total, '0') === 0) {
      throw new InvalidArgumentException('A failure record requires a financial cost.');
    }
    if ($this->decimal->compare($recoverableAmountExVat, $total) > 0) {
      throw new InvalidArgumentException('Recoverable amount cannot exceed total failure cost.');
    }

    $evidenceJson = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $now = time();
    $id = (int) $this->database->insert('brebo_finance_failure_cost')
      ->fields([
        'project_nid' => $projectNid,
        'failure_number' => trim($failureNumber),
        'status' => 'observed',
        'category' => $category,
        'source_type' => trim($sourceType),
        'source_id' => $sourceId,
        'budget_line_id' => $budgetLineId,
        'building_object_type' => $buildingObjectType !== NULL ? trim($buildingObjectType) : NULL,
        'building_object_id' => $buildingObjectId,
        'responsible_party_ref' => $responsiblePartyRef !== NULL ? trim($responsiblePartyRef) : NULL,
        'title' => trim($title),
        'cause' => trim($cause),
        'consequence' => trim($consequence),
        'preventive_measure' => trim($preventiveMeasure),
        'labour_cost_ex_vat' => $normalized['labour'],
        'material_cost_ex_vat' => $normalized['material'],
        'equipment_cost_ex_vat' => $normalized['equipment'],
        'subcontracting_cost_ex_vat' => $normalized['subcontracting'],
        'other_cost_ex_vat' => $normalized['other'],
        'total_cost_ex_vat' => $total,
        'recoverable_amount_ex_vat' => $recoverableAmountExVat,
        'recovered_amount_ex_vat' => '0.0000',
        'net_failure_cost_ex_vat' => $total,
        'owner_uid' => $ownerUid,
        'due_date' => $dueDate,
        'evidence_payload' => $evidenceJson,
        'evidence_hash' => hash('sha256', $evidenceJson),
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ])
      ->execute();

    $this->audit($projectNid, $id, 'failure_recorded', NULL, $this->hash($this->load($id)), [
      'total_cost_ex_vat' => $total,
      'evidence_hash' => hash('sha256', $evidenceJson),
    ], 'Initial evidence-backed failure-cost record.', $userId, $now);
    return $id;
  }

  /**
   * Applies four-eyes validation to cause, cost and responsibility.
   */
  public function validate(int $failureId, string $validationNote, int $validatorUid): void {
    if (trim($validationNote) === '' || $validatorUid <= 0) {
      throw new InvalidArgumentException('Failure validation requires a note and validator.');
    }
    $failure = $this->requireStatus($failureId, ['observed']);
    if ((int) $failure['created_by'] === $validatorUid) {
      throw new UnexpectedValueException('The recorder cannot validate their own failure-cost record.');
    }

    $status = $this->decimal->compare((string) $failure['recoverable_amount_ex_vat'], '0') > 0
      ? 'recovery_pending'
      : 'validated';
    $this->updateAndAudit($failure, 'failure_validated', [
      'status' => $status,
      'validated' => time(),
      'validated_by' => $validatorUid,
    ], ['validation_note' => trim($validationNote)], trim($validationNote), $validatorUid);
  }

  public function recordRecovery(
    int $failureId,
    string $recoveredAmountExVat,
    string $recoveryReference,
    int $userId,
  ): void {
    $this->assertNonNegative($recoveredAmountExVat, 'Recovered amount');
    if (trim($recoveryReference) === '' || $userId <= 0) {
      throw new InvalidArgumentException('Recovery requires an external reference and user.');
    }
    $failure = $this->requireStatus($failureId, ['recovery_pending']);
    if ($this->decimal->compare($recoveredAmountExVat, (string) $failure['recoverable_amount_ex_vat']) > 0) {
      throw new InvalidArgumentException('Recovered amount cannot exceed the validated recoverable amount.');
    }

    $net = $this->decimal->subtract((string) $failure['total_cost_ex_vat'], $recoveredAmountExVat);
    $status = $this->decimal->compare($recoveredAmountExVat, (string) $failure['recoverable_amount_ex_vat']) === 0
      ? 'validated'
      : 'recovery_pending';
    $this->updateAndAudit($failure, 'recovery_recorded', [
      'status' => $status,
      'recovered_amount_ex_vat' => $recoveredAmountExVat,
      'net_failure_cost_ex_vat' => $net,
      'recovery_reference' => trim($recoveryReference),
    ], [], 'Recovery verified against external credit or payment evidence.', $userId);
  }

  /**
   * @param array<string, mixed> $closureEvidence
   */
  public function close(
    int $failureId,
    string $closureNote,
    array $closureEvidence,
    int $closerUid,
  ): void {
    if (trim($closureNote) === '' || $closureEvidence === [] || $closerUid <= 0) {
      throw new InvalidArgumentException('Closure requires a note, evidence and responsible user.');
    }
    $failure = $this->requireStatus($failureId, ['validated', 'recovery_pending']);
    if ((int) $failure['owner_uid'] === $closerUid) {
      throw new UnexpectedValueException('The action owner cannot verify final closure.');
    }

    $outstandingRecovery = $this->decimal->compare(
      (string) $failure['recovered_amount_ex_vat'],
      (string) $failure['recoverable_amount_ex_vat'],
    ) < 0;
    if ($outstandingRecovery && empty($closureEvidence['unrecovered_decision_ref'])) {
      throw new UnexpectedValueException('Unrecovered value requires an explicit authorized decision reference.');
    }

    $closureJson = json_encode($closureEvidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $this->updateAndAudit($failure, 'failure_closed', [
      'status' => 'closed',
      'closed' => time(),
      'closed_by' => $closerUid,
      'closure_evidence' => $closureJson,
    ], [
      'closure_evidence_hash' => hash('sha256', $closureJson),
      'unrecovered_amount_ex_vat' => $this->decimal->subtract(
        (string) $failure['recoverable_amount_ex_vat'],
        (string) $failure['recovered_amount_ex_vat'],
      ),
    ], trim($closureNote), $closerUid);
  }

  /**
   * @param list<string> $statuses
   *
   * @return array<string, mixed>
   */
  private function requireStatus(int $failureId, array $statuses): array {
    $failure = $this->load($failureId);
    if (!in_array($failure['status'], $statuses, TRUE)) {
      throw new UnexpectedValueException('Failure-cost status does not allow this transition.');
    }
    return $failure;
  }

  /**
   * @return array<string, mixed>
   */
  private function load(int $failureId): array {
    $failure = $this->database->select('brebo_finance_failure_cost', 'f')
      ->fields('f')
      ->condition('id', $failureId)
      ->execute()
      ->fetchAssoc();
    if ($failure === FALSE) {
      throw new UnexpectedValueException('Failure-cost record does not exist.');
    }
    return $failure;
  }

  /**
   * @param array<string, mixed> $failure
   * @param array<string, mixed> $fields
   * @param array<string, mixed> $payload
   */
  private function updateAndAudit(
    array $failure,
    string $action,
    array $fields,
    array $payload,
    string $reason,
    int $userId,
  ): void {
    $before = $this->hash($failure);
    $now = time();
    $this->database->update('brebo_finance_failure_cost')
      ->fields($fields + ['changed' => $now, 'changed_by' => $userId])
      ->condition('id', $failure['id'])
      ->execute();
    $after = $this->load((int) $failure['id']);
    $this->audit(
      (int) $failure['project_nid'],
      (int) $failure['id'],
      $action,
      $before,
      $this->hash($after),
      $payload,
      $reason,
      $userId,
      $now,
    );
  }

  private function assertNonNegative(string $value, string $label): void {
    if ($this->decimal->compare($value, '0') < 0) {
      throw new InvalidArgumentException("$label may not be negative.");
    }
  }

  private function validDate(string $date): bool {
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== FALSE && $parsed->format('Y-m-d') === $date;
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
    int $failureId,
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
        'entity_type' => 'failure_cost',
        'entity_id' => $failureId,
        'action' => $action,
        'before_hash' => $beforeHash,
        'after_hash' => $afterHash,
        'payload' => $payload !== [] ? json_encode($payload, JSON_THROW_ON_ERROR) : NULL,
        'reason' => $reason,
        'created' => $now,
        'created_by' => $userId,
      ])
      ->execute();
  }

}
