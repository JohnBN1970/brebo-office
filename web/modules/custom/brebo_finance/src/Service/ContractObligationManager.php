<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls contractual obligations, deadlines and evidence-based closure.
 */
final class ContractObligationManager {

  private const array TYPES = [
    'payment_term',
    'notice_period',
    'claim_deadline',
    'guarantee',
    'retention',
    'bank_guarantee',
    'insurance',
    'indexation',
    'penalty',
    'delivery_condition',
    'document',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * @param array<string, mixed> $sourceEvidence
   */
  public function create(
    int $projectNid,
    int $contractId,
    string $obligationNumber,
    string $obligationType,
    string $responsibleSide,
    string $title,
    string $clauseRef,
    string $conditionText,
    string $consequence,
    string $controlMeasure,
    ?string $triggerRef,
    string $dueDate,
    string $financialExposureExVat,
    int $ownerUid,
    array $sourceEvidence,
    int $userId,
  ): int {
    if (!in_array($obligationType, self::TYPES, TRUE)) {
      throw new InvalidArgumentException('Unknown contractual obligation type.');
    }
    if (!in_array($responsibleSide, ['brebo', 'client', 'supplier', 'shared'], TRUE)) {
      throw new InvalidArgumentException('Unknown responsible contract side.');
    }
    foreach ([
      $obligationNumber,
      $title,
      $clauseRef,
      $conditionText,
      $consequence,
      $controlMeasure,
    ] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('Contract obligation requires identity, clause, condition, consequence and measure.');
      }
    }
    if (!$this->validDate($dueDate) || $ownerUid <= 0 || $userId <= 0 || $sourceEvidence === []) {
      throw new InvalidArgumentException('Contract obligation requires deadline, owner, evidence and recorder.');
    }
    if ($this->decimal->compare($financialExposureExVat, '0') < 0) {
      throw new InvalidArgumentException('Financial exposure cannot be negative.');
    }
    $contractValid = (int) $this->database->select('brebo_finance_project_contract', 'c')
      ->condition('id', $contractId)
      ->condition('project_nid', $projectNid)
      ->condition('status', 'approved')
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($contractValid !== 1) {
      throw new UnexpectedValueException('Obligation requires the approved contract of the same project.');
    }

    $sourceJson = json_encode($sourceEvidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $now = time();
    $id = (int) $this->database->insert('brebo_finance_contract_obligation')
      ->fields([
        'project_nid' => $projectNid,
        'contract_id' => $contractId,
        'obligation_number' => trim($obligationNumber),
        'obligation_type' => $obligationType,
        'responsible_side' => $responsibleSide,
        'status' => 'open',
        'title' => trim($title),
        'clause_ref' => trim($clauseRef),
        'condition_text' => trim($conditionText),
        'consequence' => trim($consequence),
        'control_measure' => trim($controlMeasure),
        'trigger_ref' => $triggerRef !== NULL ? trim($triggerRef) : NULL,
        'due_date' => $dueDate,
        'financial_exposure_ex_vat' => $financialExposureExVat,
        'owner_uid' => $ownerUid,
        'source_evidence' => $sourceJson,
        'source_hash' => hash('sha256', $sourceJson),
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ])
      ->execute();
    $this->audit($projectNid, $id, 'obligation_created', NULL, $this->hash($this->load($id)), [
      'source_hash' => hash('sha256', $sourceJson),
      'due_date' => $dueDate,
      'financial_exposure_ex_vat' => $financialExposureExVat,
    ], 'Contract condition translated into an actionable obligation.', $userId, $now);
    return $id;
  }

  /**
   * @param array<string, mixed> $completionEvidence
   */
  public function submitCompletion(
    int $obligationId,
    string $completionNote,
    array $completionEvidence,
    int $ownerUid,
  ): void {
    if (trim($completionNote) === '' || $completionEvidence === [] || $ownerUid <= 0) {
      throw new InvalidArgumentException('Completion requires owner, note and evidence.');
    }
    $obligation = $this->requireStatus($obligationId, ['open']);
    if ((int) $obligation['owner_uid'] !== $ownerUid) {
      throw new UnexpectedValueException('Only the assigned owner can submit completion.');
    }

    $evidenceJson = json_encode($completionEvidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $this->updateAndAudit($obligation, 'completion_submitted', [
      'status' => 'pending_verification',
      'completion_evidence' => $evidenceJson,
      'completion_submitted' => time(),
      'completion_submitted_by' => $ownerUid,
    ], [
      'completion_note' => trim($completionNote),
      'completion_evidence_hash' => hash('sha256', $evidenceJson),
    ], trim($completionNote), $ownerUid);
  }

  public function verifyCompletion(
    int $obligationId,
    string $decision,
    string $verificationNote,
    int $verifierUid,
  ): void {
    if (!in_array($decision, ['accepted', 'rejected'], TRUE)
      || trim($verificationNote) === ''
      || $verifierUid <= 0
    ) {
      throw new InvalidArgumentException('Verification requires accepted/rejected, a note and verifier.');
    }
    $obligation = $this->requireStatus($obligationId, ['pending_verification']);
    if ((int) $obligation['completion_submitted_by'] === $verifierUid) {
      throw new UnexpectedValueException('Completion submitter cannot verify their own evidence.');
    }

    $fields = [
      'status' => $decision === 'accepted' ? 'verified' : 'open',
      'verified' => $decision === 'accepted' ? time() : NULL,
      'verified_by' => $decision === 'accepted' ? $verifierUid : NULL,
    ];
    $this->updateAndAudit(
      $obligation,
      'completion_' . $decision,
      $fields,
      ['verification_note' => trim($verificationNote)],
      trim($verificationNote),
      $verifierUid,
    );
  }

  public function requestWaiver(int $obligationId, string $reason, int $requesterUid): void {
    if (trim($reason) === '' || $requesterUid <= 0) {
      throw new InvalidArgumentException('Waiver requires a reason and requester.');
    }
    $obligation = $this->requireStatus($obligationId, ['open']);
    $this->updateAndAudit($obligation, 'waiver_requested', [
      'status' => 'waiver_review',
      'waiver_reason' => trim($reason),
      'waiver_requested_by' => $requesterUid,
      'waiver_approved_by' => NULL,
    ], [], trim($reason), $requesterUid);
  }

  public function decideWaiver(
    int $obligationId,
    string $decision,
    string $decisionNote,
    int $approverUid,
  ): void {
    if (!in_array($decision, ['approved', 'rejected'], TRUE)
      || trim($decisionNote) === ''
      || $approverUid <= 0
    ) {
      throw new InvalidArgumentException('Waiver decision requires approved/rejected, note and approver.');
    }
    $obligation = $this->requireStatus($obligationId, ['waiver_review']);
    if ((int) $obligation['waiver_requested_by'] === $approverUid) {
      throw new UnexpectedValueException('Waiver requester cannot approve their own request.');
    }

    $this->updateAndAudit($obligation, 'waiver_' . $decision, [
      'status' => $decision === 'approved' ? 'waived' : 'open',
      'waiver_approved_by' => $decision === 'approved' ? $approverUid : NULL,
    ], ['decision_note' => trim($decisionNote)], trim($decisionNote), $approverUid);
  }

  /**
   * @param list<string> $statuses
   *
   * @return array<string, mixed>
   */
  private function requireStatus(int $obligationId, array $statuses): array {
    $obligation = $this->load($obligationId);
    if (!in_array($obligation['status'], $statuses, TRUE)) {
      throw new UnexpectedValueException('Contract-obligation status does not allow this transition.');
    }
    return $obligation;
  }

  /**
   * @return array<string, mixed>
   */
  private function load(int $obligationId): array {
    $obligation = $this->database->select('brebo_finance_contract_obligation', 'o')
      ->fields('o')
      ->condition('id', $obligationId)
      ->execute()
      ->fetchAssoc();
    if ($obligation === FALSE) {
      throw new UnexpectedValueException('Contract obligation does not exist.');
    }
    return $obligation;
  }

  /**
   * @param array<string, mixed> $obligation
   * @param array<string, mixed> $fields
   * @param array<string, mixed> $payload
   */
  private function updateAndAudit(
    array $obligation,
    string $action,
    array $fields,
    array $payload,
    string $reason,
    int $userId,
  ): void {
    $before = $this->hash($obligation);
    $now = time();
    $this->database->update('brebo_finance_contract_obligation')
      ->fields($fields + ['changed' => $now, 'changed_by' => $userId])
      ->condition('id', $obligation['id'])
      ->execute();
    $after = $this->load((int) $obligation['id']);
    $this->audit(
      (int) $obligation['project_nid'],
      (int) $obligation['id'],
      $action,
      $before,
      $this->hash($after),
      $payload,
      $reason,
      $userId,
      $now,
    );
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
    int $obligationId,
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
        'entity_type' => 'contract_obligation',
        'entity_id' => $obligationId,
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
