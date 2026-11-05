<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Receives authenticated bank snapshots from BREBO's integration layer.
 * No Moneybird invoice documents are read or used for reconciliation.
 */
final class MoneybirdBankReconciliationService {

  public function __construct(
    private readonly MoneybirdBankMutationAdapter $adapter,
    private readonly BankTransactionReconciliationManager $reconciliation,
  ) {}

  /**
   * @param array<string, mixed> $payload Result from GET /v1/accounting/bank-data
   * @return array<string, mixed>
   */
  public function process(array $payload): array {
    if (($payload['status'] ?? NULL) !== 'ok' || ($payload['provider'] ?? NULL) !== 'moneybird') {
      throw new \InvalidArgumentException('Unexpected bank snapshot source.');
    }
    $bank = $payload['bank'] ?? NULL;
    if (!is_array($bank) || ($bank['balances_available'] ?? NULL) !== FALSE) {
      throw new \InvalidArgumentException('Unverified bank snapshot.');
    }
    $activities = $this->adapter->map($bank);
    $results = [];
    foreach ($activities as $activity) {
      $results[] = $this->reconciliation->reconcile($activity);
    }
    return [
      'provider' => 'moneybird',
      'received' => count($activities),
      'reconciled' => count(array_filter($results, static fn (array $result): bool => ($result['traffic_light'] ?? '') === 'green')),
      'requires_review' => count(array_filter($results, static fn (array $result): bool => ($result['traffic_light'] ?? '') !== 'green')),
      'results' => $results,
    ];
  }
}
