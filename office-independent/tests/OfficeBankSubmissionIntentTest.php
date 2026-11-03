<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeBankSubmissionIntent.php';
use Brebo\Office\Finance\OfficeBankSubmissionIntent;
function blocked(callable $fn): void {
  try { $fn(); } catch (DomainException $e) { return; }
  throw new RuntimeException('Expected rejection');
}
$guard = ['batch_id'=>14, 'idempotency_key'=>str_repeat('a',64), 'payload_sha256'=>str_repeat('b',64)];
$intent = OfficeBankSubmissionIntent::reserve($guard, null);
blocked(static fn() => OfficeBankSubmissionIntent::reserve($guard, $intent));
$unknown = OfficeBankSubmissionIntent::outcome($intent, 'timeout');
if ($unknown['status'] !== 'reserved') throw new RuntimeException('Timeout marked complete');
$accepted = OfficeBankSubmissionIntent::outcome($intent, 'accepted', 'bank-ref-1');
if ($accepted['status'] !== 'submitted_for_authorization') throw new RuntimeException('Incorrect acceptance state');
blocked(static fn() => OfficeBankSubmissionIntent::outcome($accepted, 'accepted', 'bank-ref-2'));
echo "OfficeBankSubmissionIntentTest OK\n";
