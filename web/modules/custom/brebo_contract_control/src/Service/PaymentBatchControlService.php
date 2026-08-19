<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Final anomaly control before approved invoices enter a bank payment batch. */
final class PaymentBatchControlService {

  public function __construct(private readonly Connection $database) {}

  /** @param array<int, array<string, mixed>> $payments
   *  @return array<string, mixed>
   */
  public function inspect(array $payments): array {
    $seen = [];
    $results = [];
    $blocked = 0;

    foreach ($payments as $payment) {
      $invoiceId = (int) ($payment['invoice_id'] ?? 0);
      $supplier = trim((string) ($payment['supplier_name'] ?? ''));
      $invoiceNumber = trim((string) ($payment['invoice_number'] ?? ''));
      $amount = (float) ($payment['amount'] ?? 0);
      $ibanChanged = (bool) ($payment['iban_changed'] ?? FALSE);
      $gAccountChanged = (bool) ($payment['g_account_changed'] ?? FALSE);
      $gAccountRequired = (bool) ($payment['g_account_required'] ?? FALSE);
      $gAccountValid = (bool) ($payment['g_account_valid'] ?? !$gAccountRequired);
      $secondApprover = (int) ($payment['second_approver_uid'] ?? 0);
      $firstApprover = (int) ($payment['first_approver_uid'] ?? 0);

      $reasons = [];
      if ($invoiceId <= 0 || $supplier === '' || $invoiceNumber === '' || $amount <= 0) {
        $reasons[] = 'invalid_payment_data';
      }
      $dedup = strtolower($supplier . '|' . $invoiceNumber . '|' . number_format($amount, 2, '.', ''));
      if (isset($seen[$dedup])) {
        $reasons[] = 'duplicate_in_batch';
      }
      $seen[$dedup] = TRUE;
      if ($ibanChanged) {
        $reasons[] = 'iban_changed_requires_verification';
      }
      if ($gAccountChanged) {
        $reasons[] = 'g_account_changed_requires_verification';
      }
      if ($gAccountRequired && !$gAccountValid) {
        $reasons[] = 'invalid_g_account';
      }
      if ($firstApprover <= 0 || $secondApprover <= 0 || $firstApprover === $secondApprover) {
        $reasons[] = 'financial_four_eyes_failed';
      }

      if ($invoiceId > 0 && $this->database->schema()->tableExists('brebo_supplier_invoice')) {
        $invoice = $this->database->select('brebo_supplier_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
        if (!$invoice) {
          $reasons[] = 'invoice_not_found';
        }
        else {
          if ((string) $invoice['approval_status'] !== 'approved') {
            $reasons[] = 'invoice_not_approved';
          }
          if ((string) $invoice['match_status'] !== 'matched') {
            $reasons[] = 'invoice_not_matched';
          }
          $storedAmount = (float) ($invoice['gross_amount'] ?? 0);
          if ($storedAmount > 0 && abs($storedAmount - $amount) > 0.01) {
            $reasons[] = 'payment_amount_differs_from_invoice';
          }
        }
      }

      if ((bool) ($payment['unusual_amount'] ?? FALSE)) {
        $reasons[] = 'unusual_amount_requires_review';
      }
      if ((bool) ($payment['supplier_pattern_alert'] ?? FALSE)) {
        $reasons[] = 'supplier_pattern_alert';
      }

      $isBlocked = $reasons !== [];
      $blocked += $isBlocked ? 1 : 0;
      $results[] = [
        'invoice_id' => $invoiceId,
        'supplier_name' => $supplier,
        'amount' => round($amount, 2),
        'status' => $isBlocked ? 'blocked' : 'ready_for_bank_batch',
        'reasons' => array_values(array_unique($reasons)),
      ];
    }

    return [
      'payments' => $results,
      'total' => count($results),
      'blocked' => $blocked,
      'ready' => count($results) - $blocked,
      'batch_releasable' => $blocked === 0 && $results !== [],
      'message' => $blocked === 0 && $results !== []
        ? 'Betaalbatch voldoet aan de laatste controllercontrole.'
        : 'Betaalbatch geblokkeerd totdat alle anomalieën en vier-ogencontroles zijn opgelost.',
    ];
  }
}
