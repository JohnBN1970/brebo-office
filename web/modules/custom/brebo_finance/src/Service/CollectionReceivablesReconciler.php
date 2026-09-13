<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Reconciles collection-provider status and payments into the canonical invoice mirror. */
final class CollectionReceivablesReconciler {

  private const TRANSFER_STORE = 'brebo_finance.collection_transfer';
  private const AUDIT_STORE = 'brebo_finance.collection_reconciliation';

  private readonly VatCalculator $decimal;

  public function __construct(
    private readonly Connection $database,
    private readonly CollectionTransferManager $transferManager,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    ?VatCalculator $decimal = NULL,
  ) {
    $this->decimal = $decimal ?? new VatCalculator();
  }

  /** @return array{checked:int,updated:int,unchanged:int,failed:int} */
  public function sync(): array {
    $result = ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];
    $states = $this->keyValueFactory->get(self::TRANSFER_STORE)->getAll();

    foreach ($states as $key => $stored) {
      if (!is_array($stored)) continue;
      $invoiceId = (int) ($stored['sales_invoice_id'] ?? $key);
      if ($invoiceId <= 0 || trim((string) ($stored['external_id'] ?? '')) === '') continue;
      $result['checked']++;

      try {
        $remote = $this->transferManager->refresh($invoiceId);
        $invoice = $this->database->select('brebo_finance_sales_invoice', 'i')
          ->fields('i', ['id', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
          ->condition('id', $invoiceId)
          ->execute()
          ->fetchAssoc();
        if ($invoice === FALSE) {
          $result['failed']++;
          continue;
        }

        $providerPaid = $this->money((string) ($remote['paid_amount'] ?? '0'));
        $currentPaid = $this->money((string) $invoice['paid_amount_inc_vat']);
        $total = $this->money((string) $invoice['amount_inc_vat']);
        $effectivePaid = $this->decimal->compare($providerPaid, $currentPaid) > 0 ? $providerPaid : $currentPaid;
        if ($this->decimal->compare($effectivePaid, $total) > 0) $effectivePaid = $total;

        $status = (string) $invoice['status'];
        if ($this->decimal->compare($total, '0') > 0 && $this->decimal->compare($effectivePaid, $total) >= 0) {
          $status = 'paid';
        }
        elseif (!in_array($status, ['disputed', 'credited', 'cancelled', 'paid'], TRUE)) {
          $status = 'overdue';
        }

        if ($this->decimal->compare($effectivePaid, $currentPaid) === 0 && $status === (string) $invoice['status']) {
          $result['unchanged']++;
        }
        else {
          $this->database->update('brebo_finance_sales_invoice')
            ->fields(['paid_amount_inc_vat' => $effectivePaid, 'status' => $status, 'changed' => time(), 'changed_by' => 0])
            ->condition('id', $invoiceId)
            ->execute();
          $result['updated']++;
        }

        $this->keyValueFactory->get(self::AUDIT_STORE)->set((string) $invoiceId, [
          'sales_invoice_id' => $invoiceId,
          'provider' => (string) ($remote['provider'] ?? $stored['provider'] ?? ''),
          'external_id' => (string) ($remote['external_id'] ?? $stored['external_id'] ?? ''),
          'provider_status' => (string) ($remote['status'] ?? ''),
          'provider_paid_amount' => $providerPaid,
          'effective_paid_amount' => $effectivePaid,
          'invoice_status' => $status,
          'checked_at' => time(),
        ]);
      }
      catch (\Throwable $error) {
        $result['failed']++;
        $this->keyValueFactory->get(self::AUDIT_STORE)->set((string) $invoiceId, [
          'sales_invoice_id' => $invoiceId,
          'error' => $error->getMessage(),
          'checked_at' => time(),
        ]);
      }
    }

    return $result;
  }

  private function money(string $value): string {
    return $this->decimal->add('0', trim($value) === '' ? '0' : trim($value));
  }
}
