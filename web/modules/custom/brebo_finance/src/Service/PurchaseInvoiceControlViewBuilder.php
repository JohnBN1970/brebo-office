<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Builds the read-only purchase invoice control state for Office. */
final class PurchaseInvoiceControlViewBuilder {

  public function __construct(
    private readonly Connection $database,
    private readonly InvoicePerformanceBlockerResolver $blockerResolver,
  ) {}

  /** @return array<string,mixed> */
  public function build(int $invoiceId): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_finance_purchase_invoice')) {
      return ['available' => FALSE, 'reason' => 'purchase_invoice_table_missing'];
    }

    $invoice = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    if ($invoice === FALSE) {
      return ['available' => FALSE, 'reason' => 'purchase_invoice_missing'];
    }

    $lines = [];
    if ($schema->tableExists('brebo_finance_purchase_invoice_line')) {
      $query = $this->database->select('brebo_finance_purchase_invoice_line', 'il');
      $query->fields('il');
      if ($schema->tableExists('brebo_finance_commitment_line')) {
        $query->leftJoin('brebo_finance_commitment_line', 'cl', 'cl.id = il.commitment_line_id');
        foreach (['line_number', 'description', 'amount_ex_vat', 'unit_price_ex_vat', 'vat_code', 'vat_rate'] as $field) {
          if ($schema->fieldExists('brebo_finance_commitment_line', $field)) {
            $query->addField('cl', $field, 'commitment_' . $field);
          }
        }
        if ($schema->tableExists('brebo_finance_commitment')) {
          $query->leftJoin('brebo_finance_commitment', 'c', 'c.id = cl.commitment_id');
          foreach (['id', 'commitment_number', 'supplier_name', 'status', 'amount_ex_vat'] as $field) {
            if ($schema->fieldExists('brebo_finance_commitment', $field)) {
              $query->addField('c', $field, 'commitment_header_' . $field);
            }
          }
        }
      }
      $rows = $query->condition('il.invoice_id', $invoiceId)->orderBy('il.line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $row['blocker'] = $this->blockerResolver->resolve((int) $row['id']);
        $lines[] = $row;
      }
    }

    $paymentRelease = NULL;
    if ($schema->tableExists('brebo_finance_payment_release')) {
      $paymentRelease = $this->database->select('brebo_finance_payment_release', 'p')
        ->fields('p')
        ->condition('invoice_id', $invoiceId)
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($paymentRelease === FALSE) {
        $paymentRelease = NULL;
      }
    }

    $gAccount = NULL;
    if ($schema->tableExists('brebo_finance_g_account_instruction')) {
      $gAccount = $this->database->select('brebo_finance_g_account_instruction', 'g')
        ->fields('g')
        ->condition('source_type', 'purchase_invoice')
        ->condition('source_id', $invoiceId)
        ->condition('direction', 'outgoing')
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($gAccount === FALSE) {
        $gAccount = NULL;
      }
    }

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
