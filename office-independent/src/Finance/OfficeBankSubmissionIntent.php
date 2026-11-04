<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Pure, fail-closed state rules for durable bank-submission records.
 * Storage must atomically reserve (batch_id, idempotency_key) before network I/O.
 */
final class OfficeBankSubmissionIntent {
  public static function reserve(array $guard, ?array $existing): array {
    $batchId = $guard['batch_id'] ?? null;
    $key = $guard['idempotency_key'] ?? null;
    $hash = $guard['payload_sha256'] ?? null;
    if (!is_int($batchId) || $batchId < 1 || !is_string($key)
      || !preg_match('/^[a-f0-9]{64}$/D', $key)
      || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
      throw new \DomainException('Invalid sealed submission identity');
    }
    if ($existing !== null) {
      if ((int)($existing['batch_id'] ?? 0) !== $batchId
        || !hash_equals((string)($existing['idempotency_key'] ?? ''), $key)
        || !hash_equals((string)($existing['payload_sha256'] ?? ''), $hash)) {
        throw new \DomainException('Conflicting payment submission reservation');
      }
      // No automatic retry: the previous network outcome may be unknown.
      throw new \DomainException('Payment submission already reserved; reconcile provider status first');
    }
    return [
      'batch_id' => $batchId,
      'idempotency_key' => $key,
      'payload_sha256' => $hash,
      'status' => 'reserved',
    ];
  }

  public static function outcome(array $reservation, string $event, ?string $bankReference = null): array {
    if (($reservation['status'] ?? '') !== 'reserved') {
      throw new \DomainException('Only a reserved submission can receive its first outcome');
    }
    if ($event === 'timeout' || $event === 'transport_error') {
      return $reservation + ['note' => 'Provider result requires manual or polled verification'];
    }
    if ($event !== 'accepted' || $bankReference === null || trim($bankReference) === '') {
      throw new \DomainException('Unverified bank submission result');
    }
    $reservation['status'] = 'submitted_for_authorization';
    $reservation['bank_reference'] = trim($bankReference);
    return $reservation;
  }
}
