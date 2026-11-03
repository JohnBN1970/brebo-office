<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentSubmissionIdentity.php';
use Brebo\Office\Finance\OfficePaymentSubmissionIdentity as Identity;

$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$seal = str_repeat('a', 64);
$first = Identity::fromXml(11, $seal, $xml);
if ($first['sealed_batch_sha256'] !== $seal) throw new RuntimeException('Batch seal changed');
if ($first['payload_sha256'] !== hash('sha256', $xml)) throw new RuntimeException('Incorrect wire hash');
if ($first['idempotency_key'] !== Identity::fromXml(11, $seal, $xml)['idempotency_key']) throw new RuntimeException('Unstable key');
if ($first['idempotency_key'] === Identity::fromXml(11, $seal, $xml . ' ')['idempotency_key']) throw new RuntimeException('Changed wire reused key');
try {
  Identity::fromXml(0, $seal, $xml);
  throw new RuntimeException('Invalid batch accepted');
} catch (DomainException $expected) {}
echo "OfficePaymentSubmissionIdentityTest OK\n";
