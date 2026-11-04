<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Matches the immutable payment-batch seal algorithm used by the existing
 * BREBO Finance PaymentBatchManager. Requires all sealed fields.
 */
final class OfficePaymentCanonicalSeal {
  private const ITEM_FIELDS = [
    'instruction_type','amount','currency','creditor_name','creditor_iban',
    'creditor_bic','recipient_hash','end_to_end_id','remittance_information'
  ];

  public static function verify(array $batch, array $items): string {
    foreach (['batch_number','execution_date','currency','sealed_hash'] as $field) {
      if (!isset($batch[$field]) || !is_string($batch[$field]) || $batch[$field] === '') {
        throw new \DomainException('Incomplete canonical payment batch');
      }
    }
    if ($items === [] || !preg_match('/^[a-f0-9]{64}$/D', $batch['sealed_hash'])) {
      throw new \DomainException('Invalid payment batch seal');
    }
    $instructions = [];
    foreach ($items as $item) {
      if (!is_array($item) || !isset($item['release_id'], $item['invoice_id'])
        || (int)$item['release_id'] < 1 || (int)$item['invoice_id'] < 1) {
        throw new \DomainException('Incomplete canonical payment instruction');
      }
      foreach (self::ITEM_FIELDS as $field) {
        if (!array_key_exists($field, $item) || !is_scalar($item[$field]) && $item[$field] !== null) {
          throw new \DomainException('Incomplete canonical payment instruction');
        }
      }
      $instructions[] = [
        'release_id'=>(int)$item['release_id'],
        'invoice_id'=>(int)$item['invoice_id'],
        'instruction_type'=>$item['instruction_type'],
        'amount'=>(string)$item['amount'],
        'currency'=>$item['currency'],
        'creditor_name'=>$item['creditor_name'],
        'creditor_iban'=>$item['creditor_iban'],
        'creditor_bic'=>$item['creditor_bic'],
        'recipient_hash'=>$item['recipient_hash'],
        'end_to_end_id'=>$item['end_to_end_id'],
        'remittance_information'=>$item['remittance_information'],
      ];
    }
    $payload = ['batch_number'=>$batch['batch_number'],
      'execution_date'=>$batch['execution_date'], 'currency'=>$batch['currency'],
      'items'=>$instructions];
    $computed = hash('sha256', json_encode($payload,
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    if (!hash_equals($batch['sealed_hash'], $computed)) {
      throw new \DomainException('Canonical payment batch seal mismatch');
    }
    return $computed;
  }
}
