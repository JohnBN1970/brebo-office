<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficePaymentMandatePolicy.php';
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentExecutionPolicy.php';

use Brebo\Office\Finance\OfficePaymentExecutionPolicy as Policy;

function mustReject(callable $call): void {
  try { $call(); } catch (DomainException) { return; }
  throw new RuntimeException('Unsafe payment transition was allowed');
}

$dual = ['enabled' => true, 'mode' => 'dual', 'limit_eur' => '10000.00'];
$single = ['enabled' => true, 'mode' => 'single', 'limit_eur' => '10000.00'];
mustReject(static fn() => Policy::transition('pending_approval', 'approved', 7, 7, ['mandate' => $dual, 'total_amount' => '500.00']));
if (Policy::transition('pending_approval', 'approved', 7, 7, ['mandate' => $single, 'total_amount' => '500.00']) !== 'approved') throw new RuntimeException('Single mandate rejected');
mustReject(static fn() => Policy::transition('approved', 'submitted', 7, 8));
mustReject(static fn() => Policy::transition('submitted', 'bank_executed', 7, 8, ['bank_reference' => 'ref']));
mustReject(static fn() => Policy::transition('bank_executed', 'reconciled', 7, 8, ['bank_transaction_id' => 'tx']));
$state = Policy::transition('pending_approval', 'approved', 7, 8, ['mandate' => $dual, 'total_amount' => '500.00']);
$state = Policy::transition($state, 'submitted', 7, 8, ['idempotency_key' => 'unique-1']);
if (Policy::isPaid($state)) throw new RuntimeException('Submission marked paid');
$state = Policy::transition($state, 'bank_authorized', 7, 8, ['bank_reference' => 'ref']);
if (Policy::isPaid($state)) throw new RuntimeException('Authorization marked paid');
$state = Policy::transition($state, 'bank_executed', 7, 8, ['bank_reference' => 'ref']);
if (Policy::isPaid($state)) throw new RuntimeException('Execution without reconciliation marked paid');
$state = Policy::transition($state, 'reconciled', 7, 8, ['bank_transaction_id' => 'tx', 'accounting_reference' => 'mb-1']);
if (!Policy::isPaid($state)) throw new RuntimeException('Reconciled payment not paid');
echo "OfficePaymentExecutionPolicyTest OK\n";
