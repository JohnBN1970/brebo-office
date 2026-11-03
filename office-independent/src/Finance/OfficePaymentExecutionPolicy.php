<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Payment-state boundary for the Office-native payment center.
 * A provider submission reference is never evidence of settlement.
 */
final class OfficePaymentExecutionPolicy {
  private const TRANSITIONS = [
    'draft' => ['pending_approval'],
    'pending_approval' => ['approved', 'rejected'],
    'approved' => ['submitted', 'cancelled'],
    'submitted' => ['bank_authorized', 'bank_rejected', 'bank_unknown'],
    'bank_unknown' => ['bank_authorized', 'bank_rejected'],
    'bank_authorized' => ['bank_executed', 'bank_rejected', 'bank_unknown'],
    'bank_executed' => ['reconciled', 'reconciliation_exception'],
    'reconciliation_exception' => ['reconciled'],
  ];

  public static function transition(string $current, string $next, int $requesterId, int $actorId, array $evidence = []): string {
    if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
      throw new \DomainException('Invalid payment state transition');
    }
    if ($next === 'approved' && ($requesterId < 1 || $actorId < 1 || $requesterId === $actorId)) {
      throw new \DomainException('Independent payment approver required');
    }
    if ($next === 'submitted' && (!isset($evidence['idempotency_key']) || !is_string($evidence['idempotency_key']) || trim($evidence['idempotency_key']) === '')) {
      throw new \DomainException('Payment submission idempotency key required');
    }
    if (in_array($next, ['bank_authorized', 'bank_executed', 'bank_rejected'], true)
      && (!isset($evidence['bank_reference']) || !is_string($evidence['bank_reference']) || trim($evidence['bank_reference']) === '')) {
      throw new \DomainException('Bank status evidence required');
    }
    if ($next === 'reconciled'
      && (!isset($evidence['bank_transaction_id'], $evidence['accounting_reference'])
        || !is_string($evidence['bank_transaction_id']) || trim($evidence['bank_transaction_id']) === ''
        || !is_string($evidence['accounting_reference']) || trim($evidence['accounting_reference']) === '')) {
      throw new \DomainException('Bank and accounting reconciliation evidence required');
    }
    return $next;
  }

  public static function isPaid(string $state): bool {
    return $state === 'reconciled';
  }
}
