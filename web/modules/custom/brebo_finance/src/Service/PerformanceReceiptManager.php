<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Registers and verifies performed work against commitment lines. */
final class PerformanceReceiptManager {
  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
    private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer,
  ) {}

  public function register(
    int $commitmentLineId,
    string $amountExVat,
    string $description,
    array $evidence,
    int $userId,
  ): int {
    if ($userId <= 0 || trim($description) === '' || $evidence === []) {
      throw new InvalidArgumentException('Performance registration requires a human user, description and evidence.');
    }
    $line = $this->loadCommitmentLine($commitmentLineId);
    $amount = $this->positiveDecimal($amountExVat);
    $already = $this->registeredAmount($commitmentLineId);
    $remaining = $this->decimal->subtract((string) $line['amount_ex_vat'], $already);
    if ($this->decimal->compare($amount, $remaining) > 0) {
      throw new RuntimeException('Registered performance exceeds the remaining commitment-line amount.');
    }

    $now = time();
    $id = (int) $this->database->insert('brebo_finance_performance_receipt')->fields([
      'project_nid' => (int) $line['project_nid'],
      'commitment_line_id' => $commitmentLineId,
      'status' => 'submitted',
      'description' => trim($description),
      'amount_ex_vat' => $amount,
      'building_evidence_complete' => 0,
      'quality_accepted' => 0,
      'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'verified' => NULL,
      'verified_by' => NULL,
      'verification_note' => NULL,
      'created' => $now,
      'created_by' => $userId,
      'changed' => $now,
      'changed_by' => $userId,
    ])->execute();
    $this->audit((int) $line['project_nid'], $id, 'performance_submitted', ['amount_ex_vat' => $amount, 'commitment_line_id' => $commitmentLineId], $userId);
    $this->euroTraceSynchronizer->sync('commitment', (int) $line['commitment_id'], $userId);
    return $id;
  }

  public function verify(int $receiptId, bool $buildingEvidenceComplete, bool $qualityAccepted, string $note, int $userId): void {
    if ($userId <= 0 || trim($note) === '') throw new InvalidArgumentException('Verification requires a human verifier and note.');
    $receipt = $this->loadReceipt($receiptId);
    if ($receipt['status'] !== 'submitted') throw new UnexpectedValueException('Only submitted performance can be verified.');
    if ((int) $receipt['created_by'] === $userId) throw new RuntimeException('The submitter may not verify their own performance receipt.');
    $accepted = $buildingEvidenceComplete && $qualityAccepted;
    $now = time();
    $this->database->update('brebo_finance_performance_receipt')->fields([
      'status' => $accepted ? 'verified' : 'rejected',
      'building_evidence_complete' => $buildingEvidenceComplete ? 1 : 0,
      'quality_accepted' => $qualityAccepted ? 1 : 0,
      'verified' => $now,
      'verified_by' => $userId,
      'verification_note' => trim($note),
      'changed' => $now,
      'changed_by' => $userId,
    ])->condition('id', $receiptId)->execute();
    $line = $this->loadCommitmentLine((int) $receipt['commitment_line_id']);
    $this->audit((int) $line['project_nid'], $receiptId, $accepted ? 'performance_verified' : 'performance_rejected', [
      'building_evidence_complete' => $buildingEvidenceComplete,
      'quality_accepted' => $qualityAccepted,
      'note' => trim($note),
    ], $userId);
    $this->euroTraceSynchronizer->sync('commitment', (int) $line['commitment_id'], $userId);
  }

  private function loadCommitmentLine(int $id): array {
    $q = $this->database->select('brebo_finance_commitment_line', 'l');
    $q->join('brebo_finance_commitment', 'c', 'c.id = l.commitment_id');
    $q->fields('l');
    $q->addField('c', 'project_nid');
    $q->addField('c', 'id', 'commitment_id');
    $row = $q->condition('l.id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) throw new UnexpectedValueException('Commitment line does not exist.');
    return $row;
  }

  private function loadReceipt(int $id): array {
    $row = $this->database->select('brebo_finance_performance_receipt', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) throw new UnexpectedValueException('Performance receipt does not exist.');
    return $row;
  }

  private function registeredAmount(int $commitmentLineId): string {
    $q = $this->database->select('brebo_finance_performance_receipt', 'r');
    $q->condition('commitment_line_id', $commitmentLineId)->condition('status', ['rejected'], 'NOT IN');
    $q->addExpression('COALESCE(SUM(amount_ex_vat), 0)', 'registered_total');
    return (string) $q->execute()->fetchField();
  }

  private function positiveDecimal(string $value): string {
    $normalized = str_replace(',', '.', trim($value));
    if ($this->decimal->compare($normalized, '0') <= 0) throw new InvalidArgumentException('Performance amount must be greater than zero.');
    return $normalized;
  }

  private function audit(int $projectNid, int $receiptId, string $action, array $payload, int $userId): void {
    $this->database->insert('brebo_finance_audit')->fields([
      'project_nid' => $projectNid,
      'entity_type' => 'performance_receipt',
      'entity_id' => $receiptId,
      'action' => $action,
      'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'reason' => 'Performance evidence and quality verification for financial three-way matching.',
      'created' => time(),
      'created_by' => $userId,
    ])->execute();
  }
}
