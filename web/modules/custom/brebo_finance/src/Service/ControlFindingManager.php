<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Controls ownership and evidence-based closure of financial findings.
 */
final class ControlFindingManager {

  public function __construct(private readonly Connection $database) {}

  /**
   * Assigns an open finding to a human owner with a real deadline.
   */
  public function assign(
    int $findingId,
    int $ownerUid,
    string $dueDate,
    string $reason,
    int $actorUid,
  ): void {
    if ($ownerUid <= 0 || $actorUid <= 0) {
      throw new InvalidArgumentException('Finding assignment requires valid human users.');
    }
    if (!$this->validDate($dueDate) || $dueDate < date('Y-m-d')) {
      throw new InvalidArgumentException('Finding deadline must be today or later in YYYY-MM-DD format.');
    }
    if (trim($reason) === '') {
      throw new InvalidArgumentException('Finding assignment requires a reason.');
    }

    $finding = $this->load($findingId);
    if ($finding['status'] !== 'open') {
      throw new UnexpectedValueException('Only an open finding can be assigned.');
    }

    $beforeHash = $this->hash($finding);
    $now = time();
    $this->database->update('brebo_finance_control_finding')
      ->fields([
        'owner_uid' => $ownerUid,
        'due_date' => $dueDate,
        'changed' => $now,
        'changed_by' => $actorUid,
      ])
      ->condition('id', $findingId)
      ->execute();

    $this->audit($finding, 'assigned', $beforeHash, [
      'owner_uid' => $ownerUid,
      'due_date' => $dueDate,
    ], trim($reason), $actorUid, $now);
  }

  /**
   * Submits evidence for four-eyes verification; this does not close the item.
   */
  public function submitResolution(
    int $findingId,
    string $resolutionNote,
    array $evidence,
    int $actorUid,
  ): void {
    if ($actorUid <= 0) {
      throw new InvalidArgumentException('Resolution submission requires a human user.');
    }
    if (trim($resolutionNote) === '' || $evidence === []) {
      throw new InvalidArgumentException('Resolution requires a note and explicit evidence.');
    }

    $finding = $this->load($findingId);
    if ($finding['status'] !== 'open') {
      throw new UnexpectedValueException('Only an open finding can be submitted for verification.');
    }
    if ((int) ($finding['owner_uid'] ?? 0) !== $actorUid) {
      throw new UnexpectedValueException('Only the assigned owner can submit the resolution.');
    }

    $beforeHash = $this->hash($finding);
    $now = time();
    $evidenceJson = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    $this->database->update('brebo_finance_control_finding')
      ->fields([
        'status' => 'pending_verification',
        'resolution_note' => trim($resolutionNote),
        'resolution_evidence' => $evidenceJson,
        'resolution_submitted_by' => $actorUid,
        'resolved' => NULL,
        'resolved_by' => NULL,
        'resolution_verified_by' => NULL,
        'changed' => $now,
        'changed_by' => $actorUid,
      ])
      ->condition('id', $findingId)
      ->execute();

    $this->audit($finding, 'resolution_submitted', $beforeHash, [
      'evidence_hash' => hash('sha256', $evidenceJson),
    ], trim($resolutionNote), $actorUid, $now);
  }

  /**
   * Applies four-eyes verification to an evidence-backed resolution.
   */
  public function verifyResolution(
    int $findingId,
    string $decision,
    string $verificationNote,
    int $verifierUid,
  ): void {
    if (!in_array($decision, ['accepted', 'rejected'], TRUE)) {
      throw new InvalidArgumentException('Resolution verification must be accepted or rejected.');
    }
    if ($verifierUid <= 0 || trim($verificationNote) === '') {
      throw new InvalidArgumentException('Verification requires a human verifier and note.');
    }

    $finding = $this->load($findingId);
    if ($finding['status'] !== 'pending_verification') {
      throw new UnexpectedValueException('A pending resolution is required.');
    }
    if ((int) $finding['resolution_submitted_by'] === $verifierUid) {
      throw new UnexpectedValueException('A submitter cannot verify their own resolution.');
    }
    if (empty($finding['resolution_evidence'])) {
      throw new UnexpectedValueException('A finding cannot be closed without resolution evidence.');
    }

    $beforeHash = $this->hash($finding);
    $now = time();
    $fields = [
      'status' => $decision === 'accepted' ? 'resolved_verified' : 'open',
      'resolution_verified_by' => $verifierUid,
      'changed' => $now,
      'changed_by' => $verifierUid,
    ];
    if ($decision === 'accepted') {
      $fields['resolved'] = $now;
      $fields['resolved_by'] = $verifierUid;
    }
    else {
      $fields['resolved'] = NULL;
      $fields['resolved_by'] = NULL;
      $fields['resolution_note'] = trim((string) $finding['resolution_note'])
        . "\n\nVerificatie afgewezen: " . trim($verificationNote);
    }

    $this->database->update('brebo_finance_control_finding')
      ->fields($fields)
      ->condition('id', $findingId)
      ->execute();

    $this->audit($finding, 'resolution_' . $decision, $beforeHash, [
      'verification_note' => trim($verificationNote),
      'resolution_evidence_hash' => hash('sha256', (string) $finding['resolution_evidence']),
    ], trim($verificationNote), $verifierUid, $now);
  }

  /**
   * @return array<string, mixed>
   */
  private function load(int $findingId): array {
    $finding = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f')
      ->condition('id', $findingId)
      ->execute()
      ->fetchAssoc();
    if ($finding === FALSE) {
      throw new UnexpectedValueException('Financial control finding does not exist.');
    }
    return $finding;
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
   * @param array<string, mixed> $finding
   * @param array<string, mixed> $payload
   */
  private function audit(
    array $finding,
    string $action,
    string $beforeHash,
    array $payload,
    string $reason,
    int $actorUid,
    int $now,
  ): void {
    $after = $this->load((int) $finding['id']);
    $this->database->insert('brebo_finance_audit')
      ->fields([
        'project_nid' => $finding['project_nid'],
        'entity_type' => 'control_finding',
        'entity_id' => $finding['id'],
        'action' => $action,
        'before_hash' => $beforeHash,
        'after_hash' => $this->hash($after),
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'reason' => $reason,
        'created' => $now,
        'created_by' => $actorUid,
      ])
      ->execute();
  }

}
