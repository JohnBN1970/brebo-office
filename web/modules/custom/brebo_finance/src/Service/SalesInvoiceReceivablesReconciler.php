<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Reconciles Moneybird receivable state into existing BREBO sales invoices. */
final class SalesInvoiceReceivablesReconciler {

  public function __construct(
    private readonly Connection $database,
    private readonly SalesInvoiceReceivablesIntegrationClient $client,
    private readonly BillingControlManager $billingControlManager,
    private readonly ReceivablesReconciliationMonitor $monitor,
  ) {}

  /** @return array{received:int,updated:int,unchanged:int,unmatched:int} */
  public function sync(): array {
    $startedAt = time();
    try {
      $received = $this->client->fetchAll();
      $result = ['received' => count($received), 'updated' => 0, 'unchanged' => 0, 'unmatched' => 0];
      $recordedAt = time();

      foreach ($received as $source) {
        $moneybirdId = trim((string) ($source['id'] ?? ''));
        if ($moneybirdId === '') continue;
        $existing = $this->database->select('brebo_finance_sales_invoice', 'i')->fields('i')->condition('moneybird_id', $moneybirdId)->execute()->fetchAssoc();
        if ($existing === FALSE) {
          $result['unmatched']++;
          continue;
        }

        $totalInc = $this->decimal((string) ($source['total_price_incl_tax'] ?? $existing['amount_inc_vat']));
        $totalEx = $this->decimal((string) ($source['total_price_excl_tax'] ?? $existing['amount_ex_vat']));
        $paid = $this->decimal((string) ($source['paid_amount'] ?? '0'));
        $vat = number_format((float) $totalInc - (float) $totalEx, 4, '.', '');
        $status = $this->status((string) ($source['state'] ?? ''), $paid, $totalInc, (string) ($source['due_date'] ?? $existing['due_date']));
        $sourceHash = hash('sha256', json_encode([
          'moneybird_id' => $moneybirdId,
          'invoice_id' => $source['invoice_id'] ?? NULL,
          'state' => $source['state'] ?? NULL,
          'invoice_date' => $source['invoice_date'] ?? NULL,
          'due_date' => $source['due_date'] ?? NULL,
          'amount_ex_vat' => $totalEx,
          'amount_inc_vat' => $totalInc,
          'paid_amount' => $paid,
          'version' => $source['version'] ?? NULL,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        $beforeHash = (string) ($existing['source_hash'] ?? '');
        if ($beforeHash !== '' && hash_equals($beforeHash, $sourceHash)) {
          $result['unchanged']++;
          continue;
        }

        $existingDisputeReason = trim((string) ($existing['dispute_reason'] ?? ''));
        $salesInvoiceId = $this->billingControlManager->synchronizeMoneybirdInvoice([
          'project_nid' => (int) $existing['project_nid'],
          'instalment_id' => $existing['instalment_id'] !== NULL ? (int) $existing['instalment_id'] : NULL,
          'change_order_id' => $existing['change_order_id'] !== NULL ? (int) $existing['change_order_id'] : NULL,
          'moneybird_id' => $moneybirdId,
          'invoice_number' => trim((string) ($source['invoice_id'] ?? '')) ?: (string) $existing['invoice_number'],
          'invoice_date' => $this->date($source['invoice_date'] ?? NULL) ?? (string) $existing['invoice_date'],
          'due_date' => $this->date($source['due_date'] ?? NULL) ?? (string) $existing['due_date'],
          'status' => $status,
          'amount_ex_vat' => $totalEx,
          'vat_amount' => $vat,
          'amount_inc_vat' => $totalInc,
          'paid_amount_inc_vat' => $paid,
          'regular_account_amount' => (string) $existing['regular_account_amount'],
          'g_account_amount' => (string) $existing['g_account_amount'],
          'dispute_reason' => $existingDisputeReason !== '' ? $existingDisputeReason : ($status === 'disputed' ? 'Geschilstatus uit Moneybird.' : NULL),
          'source_hash' => $sourceHash,
          'recorded_at' => $recordedAt,
        ], 0);
        $this->monitor->invoiceUpdated((int) $existing['project_nid'], (int) $salesInvoiceId, $beforeHash, $sourceHash, $moneybirdId);
        $result['updated']++;
      }

      $this->monitor->succeeded($result, $startedAt);
      return $result;
    }
    catch (\Throwable $error) {
      $this->monitor->failed($error, $startedAt);
      throw $error;
    }
  }

  private function status(string $state, string $paid, string $total, string $dueDate): string {
    $state = strtolower(trim($state));
    if (in_array($state, ['cancelled', 'canceled'], TRUE)) return 'cancelled';
    if (in_array($state, ['credited', 'credit_invoice'], TRUE)) return 'credited';
    if (in_array($state, ['disputed'], TRUE)) return 'disputed';
    if ((float) $total > 0 && (float) $paid >= (float) $total) return 'paid';
    if ($state === 'late' || $state === 'overdue' || ($dueDate !== '' && $dueDate < date('Y-m-d') && (float) $paid < (float) $total)) return 'overdue';
    if (in_array($state, ['draft', 'new'], TRUE)) return 'draft';
    return 'sent';
  }

  private function decimal(string $value): string {
    return number_format(is_numeric($value) ? (float) $value : 0.0, 4, '.', '');
  }

  private function date(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : NULL;
  }

}
