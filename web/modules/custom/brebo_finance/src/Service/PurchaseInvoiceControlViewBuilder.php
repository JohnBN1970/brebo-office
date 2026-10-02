<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\PurchaseInvoiceControlRepositoryInterface;

/** Builds the read-only purchase invoice control state for Office. */
final class PurchaseInvoiceControlViewBuilder {

  public function __construct(
    private readonly PurchaseInvoiceControlRepositoryInterface $repository,
    private readonly InvoicePerformanceBlockerResolver $blockerResolver,
  ) {}

  /** @return array<string,mixed> */
  public function build(int $invoiceId): array {
    if (!$this->repository->available()) {
      return ['available' => FALSE, 'reason' => 'purchase_invoice_table_missing'];
    }

    $invoice = $this->repository->invoice($invoiceId);
    if ($invoice === NULL) {
      return ['available' => FALSE, 'reason' => 'purchase_invoice_missing'];
    }

    $lines = [];
    foreach ($this->repository->lines($invoiceId) as $row) {
      $row['blocker'] = $this->blockerResolver->resolve((int) $row['id']);
      $lines[] = $row;
    }

    $paymentRelease = $this->repository->latestPaymentRelease($invoiceId);
    $gAccount = $this->repository->latestGAccountInstruction($invoiceId);

    $lineExVat = 0.0;
    $lineVat = 0.0;
    $lineIncVat = 0.0;
    foreach ($lines as $line) {
      $lineExVat += (float) ($line['amount_ex_vat'] ?? 0);
      $lineVat += (float) ($line['vat_amount'] ?? 0);
      $lineIncVat += (float) ($line['amount_inc_vat'] ?? 0);
    }

    return [
      'available' => TRUE,
      'invoice' => $invoice,
      'lines' => $lines,
      'summary' => [
        'line_count' => count($lines),
        'line_amount_ex_vat' => $lineExVat,
        'line_vat_amount' => $lineVat,
        'line_amount_inc_vat' => $lineIncVat,
        'header_amount_ex_vat' => (float) ($invoice['amount_ex_vat'] ?? 0),
        'header_vat_amount' => (float) ($invoice['vat_amount'] ?? 0),
        'header_amount_inc_vat' => (float) ($invoice['amount_inc_vat'] ?? 0),
        'line_header_difference_ex_vat' => round($lineExVat - (float) ($invoice['amount_ex_vat'] ?? 0), 4),
        'unmatched_lines' => count(array_filter($lines, static fn(array $line): bool => (string) ($line['match_status'] ?? 'unmatched') !== 'matched')),
        'blocked_lines' => count(array_filter($lines, static fn(array $line): bool => (bool) ($line['blocker']['blocked'] ?? FALSE))),
      ],
      'g_account' => $gAccount,
      'payment_release' => $paymentRelease,
    ];
  }
}
