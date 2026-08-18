<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls additional and omitted work from observation through payment.
 */
final class ChangeOrderManager {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * @param array<string, mixed> $evidence
   */
  public function observe(
    int $projectNid,
    int $contractId,
    string $changeNumber,
    string $changeType,
    string $title,
    string $cause,
    string $consequence,
    ?string $contractBasis,
    ?int $budgetLineId,
    ?string $buildingObjectType,
    ?int $buildingObjectId,
    array $evidence,
    int $userId,
  ): int {
    $contractValid = (int) $this->database->select('brebo_finance_project_contract', 'c')
      ->condition('id', $contractId)
      ->condition('project_nid', $projectNid)
      ->condition('status', 'approved')
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($contractValid !== 1) {
      throw new UnexpectedValueException('Change order requires the approved contract of the same project.');
    }
    if (!in_array($changeType, ['additional', 'omission'], TRUE)) {
      throw new InvalidArgumentException('Change type must be additional or omission.');
    }
    foreach ([$changeNumber, $title, $cause, $consequence] as $required) {
      if (trim($required) === '') {
        throw new InvalidArgumentException('Change number, title, cause and consequence are required.');
      }
    }
    if ($evidence === [] || $userId <= 0) {
      throw new InvalidArgumentException('A change observation requires evidence and a responsible user.');
    }
    if (($buildingObjectType === NULL) !== ($buildingObjectId === NULL)) {
      throw new InvalidArgumentException('Building-object type and identifier must be supplied together.');
    }

    $evidenceJson = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $now = time();
    $id = (int) $this->database->insert('brebo_finance_change_order')
      ->fields([
        'project_nid' => $projectNid,
        'contract_id' => $contractId,
        'change_number' => trim($changeNumber),
        'change_type' => $changeType,
        'status' => 'observed',
        'title' => trim($title),
        'cause' => trim($cause),
        'consequence' => trim($consequence),
        'contract_basis' => $contractBasis !== NULL ? trim($contractBasis) : NULL,
        'budget_line_id' => $budgetLineId,
        'building_object_type' => $buildingObjectType !== NULL ? trim($buildingObjectType) : NULL,
        'building_object_id' => $buildingObjectId,
        'cost_amount_ex_vat' => '0.0000',
        'sales_amount_ex_vat' => '0.0000',
        'margin_amount_ex_vat' => '0.0000',
        'margin_pct' => NULL,
        'vat_code' => 'NL_21',
        'vat_rate' => '21.0000',
        'vat_amount' => '0.0000',
        'sales_amount_inc_vat' => '0.0000',
        'evidence_payload' => $evidenceJson,
        'evidence_hash' => hash('sha256', $evidenceJson),
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ])
      ->execute();
    $this->audit($projectNid, $id, 'observed', NULL, $this->hash($this->load($id)), [
      'evidence_hash' => hash('sha256', $evidenceJson),
    ], 'First controlled registration of project scope deviation.', $userId, $now);
    return $id;
  }

  public function price(
    int $changeId,
    string $costExVat,
    string $salesExVat,
    string $vatCode,
    string $vatRate,
    string $reason,
    int $userId,
  ): void {
    $this->assertPositive($costExVat, 'Change cost');
    $this->assertPositive($salesExVat, 'Change sales amount');
    if (trim($reason) === '' || trim($vatCode) === '') {
      throw new InvalidArgumentException('Pricing requires a reason and VAT code.');
    }
    $change = $this->requireStatus($changeId, ['observed', 'priced']);
    $vat = $this->decimal->calculate($salesExVat, $vatRate);
    $margin = $change['change_type'] === 'additional'
      ? $this->decimal->subtract($salesExVat, $costExVat)
      : $this->decimal->subtract($costExVat, $salesExVat);
    $marginPct = $this->decimal->percentage($margin, $salesExVat);
    $this->updateAndAudit($change, 'priced', [
      'status' => 'priced',
      'cost_amount_ex_vat' => $costExVat,
      'sales_amount_ex_vat' => $salesExVat,
      'margin_amount_ex_vat' => $margin,
      'margin_pct' => $marginPct,
      'vat_code' => trim($vatCode),
      'vat_rate' => $vat->vatRate,
      'vat_amount' => $vat->vatAmount,
      'sales_amount_inc_vat' => $vat->amountIncVat,
    ], [], trim($reason), $userId);
  }

  public function markOffered(int $changeId, string $offerReference, int $userId): void {
    if (trim($offerReference) === '') {
      throw new InvalidArgumentException('An offer reference is required.');
    }
    $change = $this->requireStatus($changeId, ['priced']);
    $this->updateAndAudit($change, 'offered', [
      'status' => 'offered',
      'offer_ref' => trim($offerReference),
      'offered_at' => time(),
    ], ['offer_reference' => trim($offerReference)], 'Change order formally offered to client.', $userId);
  }

  public function recordClientDecision(
    int $changeId,
    string $decision,
    string $clientDecisionBy,
    string $approvalReference,
    int $userId,
  ): void {
    if (!in_array($decision, ['approved', 'rejected'], TRUE)) {
      throw new InvalidArgumentException('Client decision must be approved or rejected.');
    }
    if (trim($clientDecisionBy) === '' || trim($approvalReference) === '') {
      throw new InvalidArgumentException('Client identity and traceable decision reference are required.');
    }
    $change = $this->requireStatus($changeId, ['offered']);
    $transaction = $this->database->startTransaction();
    try {
      $this->updateAndAudit($change, 'client_' . $decision, [
        'status' => 'client_' . $decision,
        'client_decision_at' => time(),
        'client_decision_by' => trim($clientDecisionBy),
        'client_approval_ref' => trim($approvalReference),
      ], [], 'Recorded traceable client decision.', $userId);
      if ($decision === 'approved') {
        $this->createApprovedRevenueMutation($change, trim($approvalReference), $userId);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function requestExecutionAtRisk(int $changeId, string $reason, int $requesterUid): void {
    if (trim($reason) === '' || $requesterUid <= 0) {
      throw new InvalidArgumentException('At-risk execution requires a requester and explicit reason.');
    }
    $change = $this->requireStatus($changeId, ['observed', 'priced', 'offered']);
    $this->updateAndAudit($change, 'risk_requested', [
      'status' => 'risk_review',
      'execution_risk_reason' => trim($reason),
      'execution_risk_requested_by' => $requesterUid,
      'execution_risk_approved_by' => NULL,
    ], [], trim($reason), $requesterUid);
  }

  public function approveExecutionAtRisk(int $changeId, string $decisionNote, int $approverUid): void {
    if (trim($decisionNote) === '' || $approverUid <= 0) {
      throw new InvalidArgumentException('At-risk approval requires an approver and note.');
    }
    $change = $this->requireStatus($changeId, ['risk_review']);
    if ((int) $change['execution_risk_requested_by'] === $approverUid) {
      throw new UnexpectedValueException('The requester cannot approve execution at risk.');
    }
    $this->updateAndAudit($change, 'risk_approved', [
      'status' => 'risk_accepted',
      'execution_risk_approved_by' => $approverUid,
    ], [], trim($decisionNote), $approverUid);
  }

  public function markExecuted(int $changeId, string $executionEvidenceReference, int $userId): void {
    if (trim($executionEvidenceReference) === '') {
      throw new InvalidArgumentException('Execution evidence is required.');
    }
    $change = $this->requireStatus($changeId, ['client_approved', 'risk_accepted']);
    $this->updateAndAudit($change, 'executed', [
      'status' => 'executed',
      'executed_at' => time(),
    ], ['execution_evidence_reference' => trim($executionEvidenceReference)], 'Scope change demonstrably executed.', $userId);
  }

  public function markInvoiced(int $changeId, string $invoiceReference, int $userId): void {
    if (trim($invoiceReference) === '') {
      throw new InvalidArgumentException('Invoice reference is required.');
    }
    $change = $this->requireStatus($changeId, ['executed']);
    $this->updateAndAudit($change, 'invoiced', [
      'status' => 'invoiced',
      'invoice_ref' => trim($invoiceReference),
      'invoiced_at' => time(),
    ], [], 'Change order linked to issued invoice.', $userId);
  }

  public function markPaid(int $changeId, string $paymentReference, int $userId): void {
    if (trim($paymentReference) === '') {
      throw new InvalidArgumentException('Bank or Moneybird payment reference is required.');
    }
    $change = $this->requireStatus($changeId, ['invoiced']);
    $this->updateAndAudit($change, 'paid', [
      'status' => 'paid',
      'payment_ref' => trim($paymentReference),
      'paid_at' => time(),
    ], [], 'Change order payment verified against external source.', $userId);
  }

  /**
   * Creates the one approved contract-revenue mutation for client approval.
   *
   * @param array<string, mixed> $change
   */
  private function createApprovedRevenueMutation(array $change, string $approvalReference, int $userId): void {
    $amountExVat = (string) $change['sales_amount_ex_vat'];
    $vatAmount = (string) $change['vat_amount'];
    $amountIncVat = (string) $change['sales_amount_inc_vat'];
    if ($change['change_type'] === 'omission') {
      $amountExVat = $this->decimal->subtract('0', $amountExVat);
      $vatAmount = $this->decimal->subtract('0', $vatAmount);
      $amountIncVat = $this->decimal->subtract('0', $amountIncVat);
    }
    $now = time();
    $this->database->insert('brebo_finance_revenue_mutation')
      ->fields([
        'project_nid' => $change['project_nid'],
        'contract_id' => $change['contract_id'],
        'mutation_number' => $change['change_number'],
        'mutation_type' => $change['change_type'],
        'description' => $change['title'],
        'cause' => 'approved_change_order',
        'status' => 'approved',
        'amount_ex_vat' => $amountExVat,
        'vat_amount' => $vatAmount,
        'amount_inc_vat' => $amountIncVat,
        'client_approval_ref' => $approvalReference,
        'approved' => $now,
        'approved_by' => $userId,
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ])
      ->execute();
  }

  /**
   * @param list<string> $statuses
   *
   * @return array<string, mixed>
   */
  private function requireStatus(int $changeId, array $statuses): array {
    $change = $this->load($changeId);
    if (!in_array($change['status'], $statuses, TRUE)) {
      throw new UnexpectedValueException(sprintf(
        'Change order status %s does not allow this transition.',
        $change['status'],
      ));
    }
    return $change;
  }

  /**
   * @return array<string, mixed>
   */
  private function load(int $changeId): array {
    $change = $this->database->select('brebo_finance_change_order', 'c')
      ->fields('c')
      ->condition('id', $changeId)
      ->execute()
      ->fetchAssoc();
    if ($change === FALSE) {
      throw new UnexpectedValueException('Change order does not exist.');
    }
    return $change;
  }

  /**
   * @param array<string, mixed> $change
   * @param array<string, mixed> $fields
   * @param array<string, mixed> $payload
   */
  private function updateAndAudit(
    array $change,
    string $action,
    array $fields,
    array $payload,
    string $reason,
    int $userId,
  ): void {
    if ($userId <= 0) {
      throw new InvalidArgumentException('A responsible human user is required.');
    }
    $before = $this->hash($change);
    $now = time();
    $this->database->update('brebo_finance_change_order')
      ->fields($fields + ['changed' => $now, 'changed_by' => $userId])
      ->condition('id', $change['id'])
      ->execute();
    $after = $this->load((int) $change['id']);
    $this->audit(
      (int) $change['project_nid'],
      (int) $change['id'],
      $action,
      $before,
      $this->hash($after),
      $payload,
      $reason,
      $userId,
      $now,
    );
  }

  private function assertPositive(string $value, string $label): void {
    if ($this->decimal->compare($value, '0') <= 0) {
      throw new InvalidArgumentException("$label must be greater than zero.");
    }
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
    int $changeId,
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
        'entity_type' => 'change_order',
        'entity_id' => $changeId,
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
