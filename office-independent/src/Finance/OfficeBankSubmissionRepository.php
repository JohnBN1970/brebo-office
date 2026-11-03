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
      $statement->execute([
        'batch_id' => $intent['batch_id'],
        'idempotency_key' => $intent['idempotency_key'],
        'payload_sha256' => $intent['payload_sha256'],
        'status' => $intent['status'],
        'requested_by' => $operatorId,
      ]);
    } catch (\PDOException $e) {
      // Unique constraints and any storage failures must block the bank call.
      throw new \DomainException('Payment submission reservation unavailable or already exists', 0, $e);
    }
    return $intent;
  }

  public function findReserved(int $batchId): ?array {
    if ($batchId < 1) throw new \DomainException('Invalid batch');
    $stmt = $this->pdo->prepare(
      "SELECT idempotency_key, payload_sha256 FROM office_bank_submission_intents
       WHERE batch_id = :batch_id AND status = 'reserved'"
    );
    $stmt->execute(['batch_id' => $batchId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
  }

  public function claimForDispatch(int $batchId, string $hash, string $key): void {
    if ($this->pdo->inTransaction()) throw new \DomainException('Dispatch claim must commit before network call');
    $stmt = $this->pdo->prepare(
      "UPDATE office_bank_submission_intents SET status = 'dispatching', changed_at = UTC_TIMESTAMP()
       WHERE batch_id = :batch_id AND status = 'reserved'
       AND payload_sha256 = :hash AND idempotency_key = :idempotency_key"
    );
    $stmt->execute(['batch_id' => $batchId, 'hash' => $hash, 'idempotency_key' => $key]);
    if ($stmt->rowCount() !== 1) throw new \DomainException('Payment already claimed or reservation mismatch');
  }

  public function recordAccepted(int $batchId, string $bankReference): void {
    if ($batchId < 1 || trim($bankReference) === '') throw new \DomainException('Invalid bank reference');
    $statement = $this->pdo->prepare(
      "UPDATE office_bank_submission_intents
       SET status = 'submitted_for_authorization', bank_reference = :reference, changed_at = UTC_TIMESTAMP()
       WHERE batch_id = :batch_id AND status = 'dispatching'"
    );
    $statement->execute(['reference' => trim($bankReference), 'batch_id' => $batchId]);
    if ($statement->rowCount() !== 1) throw new \DomainException('Payment submission outcome cannot be recorded');
  }

  public function recordUnknown(int $batchId): void {
    if ($batchId < 1) throw new \DomainException('Invalid batch');
    $statement = $this->pdo->prepare(
      "UPDATE office_bank_submission_intents
       SET status = 'unknown', changed_at = UTC_TIMESTAMP()
       WHERE batch_id = :batch_id AND status = 'dispatching'"
    );
    $statement->execute(['batch_id' => $batchId]);
    if ($statement->rowCount() !== 1) throw new \DomainException('Unknown outcome cannot be recorded');
  }
}
