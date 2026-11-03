<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Durable reservation before any bank network call. Caller supplies the
 * Office Finance PDO and must commit before initiating the bank request.
 */
final class OfficeBankSubmissionRepository {
  public function __construct(private readonly \PDO $pdo) {}

  public function reserve(array $guard, int $operatorId): array {
    if ($operatorId < 1) throw new \DomainException('Authenticated operator required');
    $intent = OfficeBankSubmissionIntent::reserve($guard, null);
    $statement = $this->pdo->prepare(
      'INSERT INTO office_bank_submission_intents
       (batch_id, idempotency_key, payload_sha256, status, requested_by, created_at)
       VALUES (:batch_id, :idempotency_key, :payload_sha256, :status, :requested_by, UTC_TIMESTAMP())'
    );
    try {
      $statement->execute($intent + ['requested_by' => $operatorId]);
    } catch (\PDOException $e) {
      // Unique constraints and any storage failures must block the bank call.
      throw new \DomainException('Payment submission reservation unavailable or already exists', 0, $e);
    }
    return $intent;
  }

  public function recordAccepted(int $batchId, string $bankReference): void {
    if ($batchId < 1 || trim($bankReference) === '') throw new \DomainException('Invalid bank reference');
    $statement = $this->pdo->prepare(
      "UPDATE office_bank_submission_intents
       SET status = 'submitted_for_authorization', bank_reference = :reference, changed_at = UTC_TIMESTAMP()
       WHERE batch_id = :batch_id AND status = 'reserved'"
    );
    $statement->execute(['reference' => trim($bankReference), 'batch_id' => $batchId]);
    if ($statement->rowCount() !== 1) throw new \DomainException('Payment submission outcome cannot be recorded');
  }

  public function recordUnknown(int $batchId): void {
    if ($batchId < 1) throw new \DomainException('Invalid batch');
    $statement = $this->pdo->prepare(
      "UPDATE office_bank_submission_intents
       SET status = 'unknown', changed_at = UTC_TIMESTAMP()
       WHERE batch_id = :batch_id AND status = 'reserved'"
    );
    $statement->execute(['batch_id' => $batchId]);
    if ($statement->rowCount() !== 1) throw new \DomainException('Unknown outcome cannot be recorded');
  }
}
