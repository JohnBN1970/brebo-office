<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * BREBO Office bank evidence projection. Never imports invoice documents.
 * No Drupal dependency and no implicit invoice/payment matching.
 */
final class OfficeMoneybirdBankSnapshot {

  /**
   * @param array<string,mixed> $payload
   * @return array{provider:string,accounts:list<array<string,string>>,transactions:list<array<string,string>>,balances_available:bool}
   */
  public static function fromIntegrationApi(array $payload): array {
    if (($payload['status'] ?? null) !== 'ok' || ($payload['provider'] ?? null) !== 'moneybird') {
      throw new \InvalidArgumentException('Invalid bank snapshot provider');
    }
    $bank = $payload['bank'] ?? null;
    if (!is_array($bank) || ($bank['balances_available'] ?? null) !== false
      || !is_array($bank['accounts'] ?? null) || !is_array($bank['transactions'] ?? null)) {
      throw new \InvalidArgumentException('Incomplete bank snapshot');
    }
    $accounts = [];
    foreach ($bank['accounts'] as $account) {
      if (!is_array($account)) continue;
      $id = trim((string)($account['id'] ?? ''));
      $currency = strtoupper(trim((string)($account['currency'] ?? '')));
      if ($id === '' || !preg_match('/^[A-Z]{3}$/D', $currency)) continue;
      $accounts[$id] = ['id'=>$id,'name'=>(string)($account['name'] ?? ''),'identifier'=>(string)($account['identifier'] ?? ''),'currency'=>$currency];
    }
    $transactions = [];
    foreach ($bank['transactions'] as $mutation) {
      if (!is_array($mutation)) continue;
      $id = trim((string)($mutation['id'] ?? ''));
      $accountId = trim((string)($mutation['financial_account_id'] ?? ''));
      $amount = trim((string)($mutation['amount'] ?? ''));
      if ($id === '' || !isset($accounts[$accountId]) || !preg_match('/^-?\\d+(?:\\.\\d{1,4})?$/D', $amount)) continue;
      $key = 'moneybird:' . $accountId . ':' . $id;
      $transactions[$key] = [
        'provider'=>'moneybird','transaction_id'=>$key,'source_mutation_id'=>$id,
        'financial_account_id'=>$accountId,'amount'=>$amount,
        'currency'=>$accounts[$accountId]['currency'],
        'booking_date'=>(string)($mutation['date'] ?? ''),
        'description'=>(string)($mutation['message'] ?? ''),
        'end_to_end_id'=>'','counterparty_iban'=>'',
        'matching_status'=>'unverified',
      ];
    }
    return ['provider'=>'moneybird','accounts'=>array_values($accounts),'transactions'=>array_values($transactions),'balances_available'=>false];
  }
}
