<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeConfirmedPaymentEvidence.php';
require_once __DIR__ . '/../src/Finance/OfficeInvoicePaymentStatus.php';
use Brebo\Office\Finance\OfficeConfirmedPaymentEvidence;
use Brebo\Office\Finance\OfficeInvoicePaymentStatus;
$rows = [
  ['invoice_id'=>10,'allocation_id'=>'a1','bank_mutation_id'=>'m1','amount_cents'=>2000,'status'=>'confirmed','match_status'=>'unique'],
  ['invoice_id'=>10,'allocation_id'=>'a2','bank_mutation_id'=>'m2','amount_cents'=>1000,'status'=>'confirmed','match_status'=>'unique'],
  ['invoice_id'=>10,'allocation_id'=>'a3','bank_mutation_id'=>'m3','amount_cents'=>9000,'status'=>'pending','match_status'=>'unique'],
  ['invoice_id'=>10,'allocation_id'=>'a4','bank_mutation_id'=>'m4','amount_cents'=>9000,'status'=>'confirmed','match_status'=>'ambiguous'],
  ['invoice_id'=>11,'allocation_id'=>'a5','bank_mutation_id'=>'m5','amount_cents'=>500,'status'=>'confirmed','match_status'=>'unique'],
];
$paid = OfficeConfirmedPaymentEvidence::sumForInvoice(10,$rows);
if ($paid !== 3000) throw new RuntimeException('Unconfirmed or ambiguous evidence counted.');
$status = OfficeInvoicePaymentStatus::project(10000,0,$paid,'2026-11-15','2026-11-09');
if ($status['status'] !== 'partial' || $status['outstanding_cents'] !== 7000) throw new RuntimeException('Wrong payment projection.');
foreach ([array_merge($rows,[$rows[0]]),array_merge($rows,[['invoice_id'=>11,'allocation_id'=>'a1','bank_mutation_id'=>'m6','amount_cents'=>100,'status'=>'confirmed','match_status'=>'unique']]),[['invoice_id'=>10,'allocation_id'=>'x','bank_mutation_id'=>'m','amount_cents'=>-1,'status'=>'confirmed','match_status'=>'unique']]] as $bad) {
  try { OfficeConfirmedPaymentEvidence::sumForInvoice(10,$bad); throw new RuntimeException('Invalid evidence accepted.'); }
  catch (InvalidArgumentException|UnexpectedValueException $expected) {}
}
// Never count one bank mutation twice through two confirmed invoice allocations.
// A mutation may be split, but only if the source amount and split ledger are validated elsewhere.
$malformed = [
  ['invoice_id'=>'10','allocation_id'=>'string-invoice','bank_mutation_id'=>'m6','amount_cents'=>100,'status'=>'confirmed','match_status'=>'unique'],
  ['invoice_id'=>10,'allocation_id'=>'bad-amount','bank_mutation_id'=>'m7','amount_cents'=>'100','status'=>'confirmed','match_status'=>'unique'],
  ['invoice_id'=>10,'allocation_id'=>'','bank_mutation_id'=>'m8','amount_cents'=>100,'status'=>'confirmed','match_status'=>'unique'],
];
foreach ($malformed as $row) {
  try {
    OfficeConfirmedPaymentEvidence::sumForInvoice(10, [$row]);
    throw new RuntimeException('Malformed allocation accepted');
  } catch (InvalidArgumentException $expected) {}
}
// The invoice projection must reject an overpayment even if its bank evidence is confirmed.
try {
  OfficeInvoicePaymentStatus::project(1000, 0, 1001, '2026-11-15', '2026-11-09');
  throw new RuntimeException('Overpayment was accepted');
} catch (InvalidArgumentException $expected) {}
echo "OFFICE_CONFIRMED_PAYMENT_EVIDENCE_TEST=PASS\n";
