<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Safe dispatch boundary: never calls a bank until the reservation transaction
 * is committed. No automatic retry after ambiguous provider responses.
 */
final class OfficePaymentSubmissionDispatch {
  public static function send(
    array $prepared,
    \PDO $pdo,
    OfficeBankSubmissionRepository $repository,
    callable $bankSubmit
  ): array {
    if ($pdo->inTransaction()) {
      throw new \DomainException('Commit submission reservation before bank request');
    }
    $batchId = $prepared['batch_id'] ?? null;
    $xml = $prepared['pain001_xml'] ?? null;
    $hash = $prepared['payload_sha256'] ?? null;
    $key = $prepared['idempotency_key'] ?? null;
    if (!is_int($batchId) || $batchId < 1 || !is_string($xml)
      || !is_string($hash) || !hash_equals(hash('sha256', $xml), $hash)
      || !is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
      throw new \DomainException('Invalid prepared bank submission');
    }
    // Must be a persisted reservation with matching identity, not merely
    // an in-memory payload supplied by a caller.
    $record = $repository->findReserved($batchId);
    if ($record === null
      || !hash_equals($record['payload_sha256'], $hash)
      || !hash_equals($record['idempotency_key'], $key)) {
      throw new \DomainException('Committed payment submission reservation required');
    }
    // Atomic status transition is committed before any network request.
    // A crashed or timed-out dispatch stays non-retryable until reconciled.
    $repository->claimForDispatch($batchId, $hash, $key);
    try {
      $result = $bankSubmit([
        'pain001_xml' => $xml,
        'payload_sha256' => $hash,
        'idempotency_key' => $key,
      ]);
      if (!is_array($result) || !is_string($result['bank_reference'] ?? null)
        || trim($result['bank_reference']) === '') {
        throw new \UnexpectedValueException('Bank acceptance not verifiable');
      }
      $repository->recordAccepted($batchId, $result['bank_reference']);
      return ['status' => 'submitted_for_authorization', 'bank_reference' => $result['bank_reference']];
    } catch (\Throwable $error) {
      try { $repository->recordUnknown($batchId); } catch (\Throwable $recordError) {
        throw new \RuntimeException('Bank outcome uncertain and status persistence failed; manual intervention required', 0, $error);
      }
      throw new \RuntimeException('Bank outcome uncertain; do not retry without reconciliation', 0, $error);
    }
  }
}
