<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeInvoicePaymentStatus.php';

use Brebo\Office\Finance\OfficeInvoicePaymentStatus;

$cases = [
  [10000, 0, 0, '2026-11-15', '2026-11-09', false, 'open', 10000],
  [10000, 0, 3000, '2026-11-15', '2026-11-09', false, 'partial', 7000],
  [10000, 0, 3000, '2026-11-01', '2026-11-09', false, 'overdue', 7000],
  [10000, 0, 10000, '2026-11-01', '2026-11-09', false, 'paid', 0],
  [10000, 2000, 8000, '2026-11-01', '2026-11-09', false, 'paid', 0],
  [10000, 10000, 0, '2026-11-01', '2026-11-09', false, 'credited', 0],
  [10000, 0, 0, '2026-11-15', '2026-11-09', true, 'cancelled', 10000],
];
foreach ($cases as [$total, $credit, $paid, $due, $today, $cancelled, $expected, $outstanding]) {
  $actual = OfficeInvoicePaymentStatus::project($total, $credit, $paid, $due, $today, $cancelled);
  if ($actual['status'] !== $expected || $actual['outstanding_cents'] !== $outstanding) {
    throw new RuntimeException('Office payment status regression: ' . json_encode($actual));
  }
}
foreach ([[100, 0, 101], [100, 101, 0], [-1, 0, 0]] as [$total, $credit, $paid]) {
  try {
    OfficeInvoicePaymentStatus::project($total, $credit, $paid, '2026-11-15', '2026-11-09');
    throw new RuntimeException('Invalid payment amount was accepted');
  } catch (InvalidArgumentException $expected) {
    // Invalid financial evidence must fail closed.
  }
}
echo "OFFICE_PAYMENT_STATUS_TEST=PASS\n";
