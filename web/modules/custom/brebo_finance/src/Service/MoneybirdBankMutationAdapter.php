<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Maps Moneybird bank mutations to BREBO bank evidence, without invoice reads.
 *
 * The bank reconciliation manager currently persists ABN as provider; callers
 * must not feed these Moneybird activities to it until provider-aware storage
 * and matching are implemented.
 */
final class MoneybirdBankMutationAdapter {

  /**
   * @param array<string, mixed> $bank
   * @return list<array<string, string>>
   */
  public function map(array $bank): array {
    $accounts = [];
    foreach (($bank['accounts'] ?? []) as $account) {
      if (!is_array($account)) {
        continue;
      }
      $id = trim((string) ($account['id'] ?? ''));
      if ($id !== '') {
        $accounts[$id] = strtoupper(trim((string) ($account['currency'] ?? '')));
      }
    }

    $result = [];
    $seen = [];
    foreach (($bank['transactions'] ?? []) as $mutation) {
      if (!is_array($mutation)) {
        continue;
      }
      $id = trim((string) ($mutation['id'] ?? ''));
      $accountId = trim((string) ($mutation['financial_account_id'] ?? ''));
      $amount = trim((string) ($mutation['amount'] ?? ''));
      if ($id === '' || !isset($accounts[$accountId]) || $accounts[$accountId] === '' || !preg_match('/^-?\\d+(?:\\.\\d{1,4})?$/D', $amount)) {
        continue;
      }
      $transactionId = 'moneybird:' . $accountId . ':' . $id;
      if (isset($seen[$transactionId])) {
        continue;
      }
      $seen[$transactionId] = TRUE;
      $result[] = [
        'provider' => 'moneybird',
        'transaction_id' => $transactionId,
        'source_mutation_id' => $id,
        'financial_account_id' => $accountId,
        'amount' => $amount,
        'currency' => $accounts[$accountId],
        'booking_date' => trim((string) ($mutation['date'] ?? '')),
        'description' => trim((string) ($mutation['message'] ?? '')),
        // Never infer payment references, IBAN or invoice identity from prose.
        'end_to_end_id' => '',
        'counterparty_iban' => '',
      ];
    }
    return $result;
  }
}
