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
   * @return array{provider:string,accounts:list<array<string,string>>,transactions:list<array<string,string>>,balances_available:bool,period:string,booked_balances?:array|null}
   */
  public static function fromIntegrationApi(array $payload): array {
    if (($payload['status'] ?? null) !== 'ok' || ($payload['provider'] ?? null) !== 'moneybird') {
      throw new \InvalidArgumentException('Invalid bank snapshot provider');
    }
    $bank = $payload['bank'] ?? null;
    if (!is_array($bank) || ($bank['balances_available'] ?? null) !== false || ($bank['period'] ?? null) !== 'this_month'
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
    $result = ['provider'=>'moneybird','accounts'=>array_values($accounts),'transactions'=>array_values($transactions),'balances_available'=>false,'period'=>'this_month'];
    if (array_key_exists('booked_balances', $bank)) {
      $report = $bank['booked_balances'];
      $result['booked_balances'] = null;
      if ($report !== null) {
        if (!is_array($report) || ($report['source'] ?? '') !== 'moneybird_bank_balance_report'
            || ($report['period'] ?? '') !== 'this_month' || !is_string($report['retrieved_at'] ?? null)
            || !preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z$/D', $report['retrieved_at'])
            || !is_array($report['accounts'] ?? null)) throw new \InvalidArgumentException('Invalid booked balance report');
        $rows=[];
        foreach ($report['accounts'] as $row) {
          if (!is_array($row) || !is_string($row['financial_account_id'] ?? null) || !isset($accounts[$row['financial_account_id']])
              || isset($rows[$row['financial_account_id']]) || !is_string($row['amount'] ?? null)
              || !preg_match('/^-?\\d{1,30}(?:\\.\\d{1,4})?$/D', $row['amount'])) throw new \InvalidArgumentException('Invalid booked balance evidence');
          $rows[$row['financial_account_id']]=['financial_account_id'=>$row['financial_account_id'],'amount'=>$row['amount']];
        }
        $result['booked_balances']=['source'=>'moneybird_bank_balance_report','period'=>'this_month','retrieved_at'=>$report['retrieved_at'],'accounts'=>array_values($rows)];
      }
    }
    return $result;
  }
}

