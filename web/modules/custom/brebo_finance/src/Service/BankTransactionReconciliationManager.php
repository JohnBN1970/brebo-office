<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Deterministically classifies ABN AMRO mutations against BREBO payment items. */
final class BankTransactionReconciliationManager {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Reconciles one normalized bank activity.
   *
   * Exact EndToEndId + amount + currency + creditor IBAN is green. Ambiguous or
   * incomplete matches remain orange/neutral; material contradictions are red.
   */
  public function reconcile(array $activity): array {
    $transactionId = trim((string) ($activity['transaction_id'] ?? ''));
    $amount = (string) ($activity['amount'] ?? '0');
    $currency = strtoupper(trim((string) ($activity['currency'] ?? 'EUR')));
    $counterpartyIban = $this->normalizeIban((string) ($activity['counterparty_iban'] ?? ''));
    $endToEndId = trim((string) ($activity['end_to_end_id'] ?? ''));

    if ($transactionId === '' || $this->decimal->compare($amount, '0') === 0) {
      return $this->result('neutral', 'bank_activity_incomplete', 'Bankmutatie mist een stabiele referentie of bedrag.', []);
    }

    $existing = $this->database->select('brebo_finance_bank_reconciliation', 'r')
      ->fields('r')
      ->condition('bank_transaction_id', $transactionId)
      ->execute()->fetchAssoc();
    if ($existing) {
      return $this->result((string) $existing['traffic_light'], (string) $existing['reason_code'], 'Bankmutatie is al beoordeeld.', $existing);
    }

    $candidates = [];
    if ($endToEndId !== '') {
      $query = $this->database->select('brebo_finance_payment_batch_item', 'i');
      $query->join('brebo_finance_payment_batch', 'b', 'b.id = i.batch_id');
      $query->fields('i')->addField('b', 'status', 'batch_status');
      $query->condition('i.end_to_end_id', $endToEndId);
      $query->condition('b.status', ['released', 'submitted', 'executed', 'reconciled'], 'IN');
      $candidates = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    }

    if (count($candidates) === 1) {
      $item = reset($candidates);
      $amountOk = $this->decimal->compare((string) $item['amount'], ltrim($amount, '-')) === 0;
      $currencyOk = strtoupper((string) $item['currency']) === $currency;
      $ibanOk = $counterpartyIban === '' || $this->normalizeIban((string) $item['creditor_iban']) === $counterpartyIban;
      if ($amountOk && $currencyOk && $ibanOk) {
        return $this->persist($activity, $item, 'green', 'exact_brebo_bank_match', 'Bankuitvoering sluit exact aan op de vrijgegeven BREBO-betaalinstructie.');
      }
      return $this->persist($activity, $item, 'red', 'brebo_bank_material_mismatch', 'Bankuitvoering wijkt materieel af van de vrijgegeven BREBO-betaalinstructie.');
    }

    if (count($candidates) > 1) {
      return $this->persist($activity, NULL, 'red', 'duplicate_end_to_end_id', 'Dezelfde EndToEndId wijst naar meerdere BREBO-betaalinstructies.');
    }

    // No BREBO-originating instruction: retain the activity for ordinary-bank
    // matching/classification instead of silently treating it as unexplained.
    return $this->persist($activity, NULL, 'orange', 'external_bank_payment', 'Bankmutatie is buiten een BREBO-betaalrun ontstaan en vraagt automatische vervolgmatching of classificatie.');
  }

  private function persist(array $activity, ?array $item, string $light, string $reason, string $message): array {
    $this->ensureStorage();
    $now = time();
    $fields = [
      'bank_provider' => 'abnamro',
      'bank_transaction_id' => trim((string) $activity['transaction_id']),
      'booking_date' => (string) ($activity['booking_date'] ?? ''),
      'amount' => (string) ($activity['amount'] ?? '0'),
      'currency' => strtoupper((string) ($activity['currency'] ?? 'EUR')),
      'counterparty_iban' => $this->normalizeIban((string) ($activity['counterparty_iban'] ?? '')),
      'end_to_end_id' => (string) ($activity['end_to_end_id'] ?? ''),
      'batch_id' => $item ? (int) $item['batch_id'] : NULL,
      'batch_item_id' => $item ? (int) $item['id'] : NULL,
      'invoice_id' => $item ? (int) $item['invoice_id'] : NULL,
      'release_id' => $item ? (int) $item['release_id'] : NULL,
      'traffic_light' => $light,
      'reason_code' => $reason,
      'message' => $message,
      'moneybird_state' => 'pending',
      'created' => $now,
      'changed' => $now,
    ];
    $this->database->insert('brebo_finance_bank_reconciliation')->fields($fields)->execute();
    return $this->result($light, $reason, $message, $fields);
  }

  private function result(string $light, string $reason, string $message, array $evidence): array {
    return ['traffic_light' => $light, 'reason_code' => $reason, 'message' => $message, 'evidence' => $evidence];
  }

  private function normalizeIban(string $iban): string {
    return strtoupper((string) preg_replace('/\s+/', '', trim($iban)));
  }

  private function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_finance_bank_reconciliation')) {
      return;
    }
    $schema->createTable('brebo_finance_bank_reconciliation', [
      'description' => 'Bank to BREBO to Moneybird reconciliation evidence.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'bank_provider' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
        'bank_transaction_id' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
        'booking_date' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
        'amount' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE],
        'currency' => ['type' => 'varchar', 'length' => 3, 'not null' => TRUE],
        'counterparty_iban' => ['type' => 'varchar', 'length' => 34, 'not null' => FALSE],
        'end_to_end_id' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
        'batch_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'batch_item_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'invoice_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'release_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'traffic_light' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE],
        'reason_code' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'message' => ['type' => 'text', 'not null' => TRUE],
        'moneybird_state' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'pending'],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['provider_transaction' => ['bank_provider', 'bank_transaction_id']],
      'indexes' => [
        'traffic_light' => ['traffic_light'],
        'invoice_id' => ['invoice_id'],
        'batch_id' => ['batch_id'],
        'moneybird_state' => ['moneybird_state'],
      ],
    ]);
  }
}
