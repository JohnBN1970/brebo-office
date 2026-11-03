<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Fail-closed handoff of an already sealed payment batch to the bank adapter.
 * This class never performs network I/O. The caller must persist a durable
 * idempotency reservation before invoking the bank, and handle unknown outcomes.
 */
final class OfficePaymentBatchSubmissionGuard {
  public static function authorize(array $sealedPayload, int $operatorId, callable $canSubmit, callable $isRecipientUnchanged, array $mandate): array {
    if ($operatorId < 1 || $canSubmit($operatorId) !== true) {
      throw new \DomainException('Payment submission permission required');
    }
    $batch = $sealedPayload['batch'] ?? null;
    $items = $sealedPayload['items'] ?? null;
    $hash = $sealedPayload['sealed_hash'] ?? null;
    if (!is_array($batch) || !is_array($items) || $items === []
      || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)
      || ($batch['status'] ?? '') !== 'released'
      || !isset($batch['sealed_hash']) || !is_string($batch['sealed_hash'])
      || !hash_equals($batch['sealed_hash'], $hash)
      || !isset($batch['id'], $batch['created_by'], $batch['released_by'])
      || (int)$batch['created_by'] < 1 || (int)$batch['released_by'] < 1
      || !in_array(($batch['controller_verdict'] ?? ''), ['green', 'orange'], true)) {
      throw new \DomainException('Sealed and independently released batch required');
    }
    OfficePaymentMandatePolicy::authorize(
      $mandate,
      $operatorId,
      (int)$batch['created_by'],
      (int)$batch['released_by'],
      (string)($batch['control_sum'] ?? '')
    );
    $ids = [];
    foreach ($items as $item) {
      if (!is_array($item) || ($item['status'] ?? '') !== 'released'
        || ($item['currency'] ?? '') !== 'EUR'
        || ($item['instruction_type'] ?? '') !== 'regular'
        || !isset($item['invoice_id'], $item['recipient_hash'], $item['creditor_iban'], $item['amount'])
        || !is_string($item['recipient_hash']) || $item['recipient_hash'] === ''
        || !is_string($item['creditor_iban']) || $item['creditor_iban'] === ''
        || !is_numeric($item['amount']) || (float)$item['amount'] <= 0) {
        throw new \DomainException('Unsafe payment instruction');
      }
      if ($isRecipientUnchanged((int)$item['invoice_id'], $item['recipient_hash'], $operatorId) !== true) {
        throw new \DomainException('Payment recipient changed after release');
      }
      $identity = (string)($item['end_to_end_id'] ?? '');
      if ($identity === '' || isset($ids[$identity])) throw new \DomainException('Duplicate or missing payment identity');
      $ids[$identity] = true;
    }
    return [
      'batch_id' => (int)$batch['id'],
      'payload_sha256' => $hash,
      'idempotency_key' => hash('sha256', 'brebo-office-bank-v1:' . (int)$batch['id'] . ':' . $hash),
    ];
  }
}
