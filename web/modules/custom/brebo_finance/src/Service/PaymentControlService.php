<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Prevents supplier payments without complete financial approval.
 */
final class PaymentControlService {

  /**
   * @param array<string, mixed> $invoice
   * @param array<int, array<string, mixed>> $payments
   * @return array<string, mixed>
   */
  public function assess(array $invoice, array $payments = []): array {
    $blocking = [];
    $gross = max(0.0, (float) ($invoice['gross_amount'] ?? 0));
    $requiredG = max(0.0, (float) ($invoice['g_account_amount'] ?? 0));
    $paidRegular = 0.0;
    $paidG = 0.0;

    foreach ($payments as $payment) {
      if (($payment['status'] ?? 'booked') === 'cancelled') {
        continue;
      }
      $paidRegular += max(0.0, (float) ($payment['regular_amount'] ?? 0));
      $paidG += max(0.0, (float) ($payment['g_account_amount'] ?? 0));
    }

    if (($invoice['approval_status'] ?? '') !== 'approved') {
      $blocking[] = 'Betaling geblokkeerd: leveranciersfactuur is niet goedgekeurd.';
    }
    if (($invoice['match_status'] ?? '') !== 'matched') {
      $blocking[] = 'Betaling geblokkeerd: 3-way match is niet akkoord.';
    }
    if ($paidRegular + $paidG > $gross + 0.02) {
      $blocking[] = 'Betalingen overschrijden het brutofactuurbedrag.';
    }
    if ($requiredG > 0 && $paidG > $requiredG + 0.02) {
      $blocking[] = 'G-rekeningbetaling overschrijdt het vereiste G-rekeningbedrag.';
    }

    $paid = $paidRegular + $paidG;
    $remaining = max(0.0, $gross - $paid);
    $gRemaining = max(0.0, $requiredG - $paidG);

    return [
      'status' => $blocking ? 'blocked' : ($remaining <= 0.02 ? 'paid' : 'payable'),
      'blocking' => array_values(array_unique($blocking)),
      'paid_regular' => round($paidRegular, 2),
      'paid_g_account' => round($paidG, 2),
      'paid_total' => round($paid, 2),
      'remaining' => round($remaining, 2),
      'g_account_remaining' => round($gRemaining, 2),
      'payment_allowed' => !$blocking && $remaining > 0.02,
    ];
  }

}
