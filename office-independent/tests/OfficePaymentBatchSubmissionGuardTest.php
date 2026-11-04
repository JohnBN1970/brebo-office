<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficePaymentMandatePolicy.php';
require_once $src . 'OfficePaymentSubmissionIdentity.php';
require_once $src . 'OfficePaymentBatchSubmissionGuard.php';
use Brebo\Office\Finance\OfficePaymentBatchSubmissionGuard as Guard;

$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$hash = str_repeat('a', 64);
$payload = [
  'sealed_hash' => $hash,
  'batch' => ['id' => 23, 'status' => 'released', 'sealed_hash' => $hash,
    'created_by' => 5, 'released_by' => 5, 'controller_verdict' => 'green', 'control_sum' => '50.00'],
  'items' => [['status' => 'released', 'currency' => 'EUR', 'instruction_type' => 'regular',
    'invoice_id' => 9, 'recipient_hash' => 'recipient-1', 'creditor_iban' => 'NL91ABNA0417164300',
    'amount' => '50.00', 'end_to_end_id' => 'invoice-9']],
];
$mandate = ['enabled' => true, 'mode' => 'single', 'limit_eur' => '100.00'];
$allow = static fn(int $id): bool => $id === 5;
$unchanged = static fn(int $invoice, string $recipient, int $operator): bool =>
  $invoice === 9 && $recipient === 'recipient-1' && $operator === 5;
$identity = Guard::authorize($payload, 5, $allow, $unchanged, $mandate, $xml);
if ($identity['payload_sha256'] !== hash('sha256', $xml)) throw new RuntimeException('Wrong wire hash');
if ($identity['sealed_batch_sha256'] !== $hash) throw new RuntimeException('Wrong seal');
if ($identity['idempotency_key'] === Guard::authorize($payload, 5, $allow, $unchanged, $mandate, $xml . ' ')['idempotency_key']) {
  throw new RuntimeException('Wire mutation did not change identity');
}
$dual = ['enabled' => true, 'mode' => 'dual', 'limit_eur' => '100.00'];
try {
  Guard::authorize($payload, 5, $allow, $unchanged, $dual, $xml);
  throw new RuntimeException('Self-approved dual payment accepted');
} catch (DomainException $expected) {}
echo "OfficePaymentBatchSubmissionGuardTest OK\n";
