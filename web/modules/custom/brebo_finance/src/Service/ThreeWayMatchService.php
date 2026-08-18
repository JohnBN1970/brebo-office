<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Deterministic financial controls for supplier invoices.
 */
final class ThreeWayMatchService {

  /**
   * Compare approved budget, purchase order and supplier invoice amounts.
   *
   * @return array<string, mixed>
   */
  public function analyze(float $budgetAmount, float $orderAmount, float $invoiceAmount, float $tolerancePct = 1.0): array {
    $budgetAmount = max(0.0, $budgetAmount);
    $orderAmount = max(0.0, $orderAmount);
    $invoiceAmount = max(0.0, $invoiceAmount);
    $tolerancePct = max(0.0, $tolerancePct);

    $orderVariance = $orderAmount - $budgetAmount;
    $invoiceVariance = $invoiceAmount - $orderAmount;
    $allowedInvoiceVariance = $orderAmount * ($tolerancePct / 100);

    $signals = [];
    $status = 'matched';

    if ($budgetAmount <= 0 || $orderAmount <= 0) {
      $status = 'insufficient_data';
      $signals[] = 'Goedgekeurde werkbegroting en inkooporder zijn verplicht voor automatische factuurcontrole.';
    }
    elseif ($orderVariance > 0.01) {
      $status = 'budget_exceeded';
      $signals[] = 'Inkooporder overschrijdt de goedgekeurde werkbegroting.';
    }

    if ($orderAmount > 0 && abs($invoiceVariance) > $allowedInvoiceVariance + 0.01) {
      $status = $invoiceVariance > 0 ? 'invoice_exceeded' : 'invoice_under_order';
      $signals[] = 'Factuur wijkt meer af van de inkooporder dan de ingestelde tolerantie.';
    }

    return [
      'status' => $status,
      'budget_amount' => round($budgetAmount, 2),
      'order_amount' => round($orderAmount, 2),
      'invoice_amount' => round($invoiceAmount, 2),
      'order_variance' => round($orderVariance, 2),
      'invoice_variance' => round($invoiceVariance, 2),
      'tolerance_pct' => round($tolerancePct, 2),
      'automatic_approval' => $status === 'matched',
      'signals' => $signals,
    ];
  }

  /**
   * Calculate the expected G-account portion of an invoice.
   */
  public function expectedGAccount(float $grossAmount, float $percentage): float {
    return round(max(0.0, $grossAmount) * (max(0.0, min(100.0, $percentage)) / 100), 2);
  }

}
