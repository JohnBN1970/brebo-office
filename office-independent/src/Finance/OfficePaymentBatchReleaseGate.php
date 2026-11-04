<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Pure fail-closed Office payment batch release gate.
 * Does not write records, authorize operators, or transmit payments.
 */
final class OfficePaymentBatchReleaseGate {
  public static function assertReleasable(array $batch, array $items, int $operatorId, string $note): string {
    if ($operatorId < 1 || trim($note) === '') throw new \DomainException('Valid operator and release note required');
    if (($batch['status'] ?? null) !== 'reviewed'
      || !in_array(($batch['controller_verdict'] ?? null), ['green','orange'], true)
      || (int)($batch['created_by'] ?? 0) < 1
      || (int)$batch['created_by'] === $operatorId
      || (int)($batch['reviewed_by'] ?? 0) < 1
      || (int)($batch['reviewed'] ?? 0) < 1
      || ($batch['currency'] ?? null) !== 'EUR'
      || $items === []
      || (int)($batch['item_count'] ?? -1) !== count($items)) {
      throw new \DomainException('Payment batch release controls failed');
    }
    if ((int)$batch['reviewed_by'] === $operatorId) {
      throw new \DomainException('Payment batch reviewer may not release their own review');
    }
    foreach ($items as $item) {
      if (($item['status'] ?? null) !== 'prepared'
        || ($item['currency'] ?? null) !== 'EUR'
        || ($item['instruction_type'] ?? null) !== 'regular'
        || (int)($item['release_id'] ?? 0) < 1
        || (int)($item['invoice_id'] ?? 0) < 1) {
        throw new \DomainException('Unsafe payment batch item');
      }
    }
    $hash = self::payloadHash($batch, $items);
    if (!preg_match('/^[a-f0-9]{64}$/D', (string)($batch['payload_hash'] ?? ''))
      || !hash_equals($batch['payload_hash'], $hash)) {
      throw new \DomainException('Payment batch changed since review');
    }
    return $hash;
  }

  public static function payloadHash(array $batch, array $items): string {
    foreach (['batch_number','execution_date','currency'] as $field) {
      if (!is_string($batch[$field] ?? null) || $batch[$field] === '') {
        throw new \DomainException('Incomplete payment batch');
      }
    }
    $fields = ['instruction_type','amount','currency','creditor_name','creditor_iban',
      'creditor_bic','recipient_hash','end_to_end_id','remittance_information'];
    $instructions = [];
    foreach ($items as $item) {
      if ((int)($item['release_id'] ?? 0) < 1 || (int)($item['invoice_id'] ?? 0) < 1) {
        throw new \DomainException('Incomplete payment batch item');
      }
      $entry = ['release_id'=>(int)$item['release_id'],'invoice_id'=>(int)$item['invoice_id']];
      foreach ($fields as $field) {
        if (!array_key_exists($field, $item)
          || (!is_scalar($item[$field]) && $item[$field] !== null)) {
          throw new \DomainException('Incomplete payment instruction');
        }
        $entry[$field] = $field === 'amount' ? (string)$item[$field] : $item[$field];
      }
      $instructions[] = $entry;
    }
    return hash('sha256', json_encode([
      'batch_number'=>$batch['batch_number'],
      'execution_date'=>$batch['execution_date'],
      'currency'=>$batch['currency'],
      'items'=>$instructions,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
  }
}
