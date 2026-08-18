<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Performs deterministic controls before supplier invoice approval/payment.
 */
final class InvoiceControlService {

  public function __construct(
    private readonly ThreeWayMatchService $threeWayMatch,
  ) {}

  /**
   * @param array<string, mixed> $invoice
   * @param array<string, mixed> $order
   * @param array<int, array<string, mixed>> $existingInvoices
   * @return array<string, mixed>
   */
  public function assess(array $invoice, array $order, array $existingInvoices = []): array {
    $signals = [];
    $blocking = [];

    $invoiceNumber = $this->normalize((string) ($invoice['invoice_number'] ?? ''));
    $supplier = $this->normalize((string) ($invoice['supplier_name'] ?? ''));
    if ($invoiceNumber === '' || $supplier === '') {
      $blocking[] = 'Leverancier en factuurnummer zijn verplicht.';
    }

    foreach ($existingInvoices as $existing) {
      if ($this->normalize((string) ($existing['invoice_number'] ?? '')) === $invoiceNumber
        && $this->normalize((string) ($existing['supplier_name'] ?? '')) === $supplier) {
        $blocking[] = 'Mogelijke dubbele factuur: leverancier en factuurnummer bestaan al.';
        break;
      }
    }

    $net = max(0.0, (float) ($invoice['net_amount'] ?? 0));
    $vat = max(0.0, (float) ($invoice['vat_amount'] ?? 0));
    $gross = max(0.0, (float) ($invoice['gross_amount'] ?? 0));
    if (abs(($net + $vat) - $gross) > 0.02) {
      $blocking[] = 'Factuurtotaal sluit niet aan: netto + BTW is niet gelijk aan bruto.';
    }

    $vatRate = isset($invoice['vat_rate']) ? (float) $invoice['vat_rate'] : NULL;
    if ($vatRate !== NULL) {
      $expectedVat = round($net * ($vatRate / 100), 2);
      if (abs($expectedVat - $vat) > 0.02) {
        $blocking[] = 'BTW-bedrag wijkt af van het opgegeven BTW-percentage.';
      }
    }

    $budgetAmount = max(0.0, (float) ($order['budget_amount'] ?? 0));
    $orderAmount = max(0.0, (float) ($order['gross_amount'] ?? 0));
    $match = $this->threeWayMatch->analyze($budgetAmount, $orderAmount, $gross, (float) ($order['tolerance_pct'] ?? 1.0));
    if (!$match['automatic_approval']) {
      $blocking = array_merge($blocking, $match['signals']);
    }

    $gPct = max(0.0, min(100.0, (float) ($order['g_account_pct'] ?? 0)));
    $gAmount = max(0.0, (float) ($invoice['g_account_amount'] ?? 0));
    $expectedG = $this->threeWayMatch->expectedGAccount($gross, $gPct);
    if ($gPct > 0 && abs($expectedG - $gAmount) > 0.02) {
      $blocking[] = 'G-rekeningbedrag wijkt af van de afgesproken verdeling op de inkooporder.';
    }

    if (($invoice['approval_status'] ?? 'pending') !== 'approved') {
      $signals[] = 'Factuur is nog niet goedgekeurd voor betaling.';
    }

    return [
      'status' => $blocking ? 'blocked' : ($signals ? 'attention' : 'approved'),
      'blocking' => array_values(array_unique($blocking)),
      'signals' => array_values(array_unique($signals)),
      'match' => $match,
      'expected_g_account_amount' => $expectedG,
      'payment_allowed' => !$blocking && (($invoice['approval_status'] ?? '') === 'approved'),
    ];
  }

  private function normalize(string $value): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($value)) ?? '');
  }

}
