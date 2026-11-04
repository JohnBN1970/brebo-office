<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
foreach (['OfficeSepaArtifactCipher','OfficeSepaArtifactRepository','OfficeBankSubmissionIntent','OfficeBankSubmissionRepository','OfficePaymentMandatePolicy','OfficePaymentSubmissionIdentity','OfficePaymentBatchSubmissionGuard','OfficePaymentSubmissionPreparation'] as $name) {
  require_once $src . $name . '.php';
}
use Brebo\Office\Finance\OfficeSepaArtifactRepository;
use Brebo\Office\Finance\OfficeBankSubmissionRepository;
use Brebo\Office\Finance\OfficePaymentSubmissionPreparation;

if (!extension_loaded('pdo_sqlite') || !extension_loaded('sodium')) {
  throw new RuntimeException('pdo_sqlite and sodium required');
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'));
$db->exec('CREATE TABLE office_sepa_batch_artifacts (
  batch_id INTEGER UNIQUE, payload_sha256 TEXT, ciphertext BLOB,
  encryption_nonce BLOB, encryption_key_id TEXT, created_by INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE office_bank_submission_intents (
  batch_id INTEGER UNIQUE, idempotency_key TEXT UNIQUE, payload_sha256 TEXT,
  status TEXT, requested_by INTEGER, created_at TEXT)');
$artifacts = new OfficeSepaArtifactRepository($db);
$submissions = new OfficeBankSubmissionRepository($db);
$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$artifacts->store(17, 3, $xml, $key, 'key-test');
$seal = str_repeat('a', 64);
$payload = [
  'sealed_hash' => $seal,
  'batch' => ['id' => 17, 'status' => 'released', 'sealed_hash' => $seal,
    'created_by' => 3, 'released_by' => 3, 'controller_verdict' => 'green', 'control_sum' => '50.00'],
  'items' => [['status' => 'released', 'currency' => 'EUR', 'instruction_type' => 'regular',
    'invoice_id' => 9, 'recipient_hash' => 'recipient', 'creditor_iban' => 'NL91ABNA0417164300',
    'amount' => '50.00', 'end_to_end_id' => 'invoice-9']],
];
$allow = static fn(int $operator): bool => $operator === 3;
$unchanged = static fn(int $invoice, string $hash, int $operator): bool =>
  $invoice === 9 && $hash === 'recipient' && $operator === 3;
$resolveKey = static fn(string $id): string => $key;
$mandate = ['enabled' => true, 'mode' => 'single', 'limit_eur' => '100.00'];
$prepared = OfficePaymentSubmissionPreparation::prepare(
  $payload, 3, $allow, $unchanged, $mandate, $artifacts, $submissions, $resolveKey
);
if ($prepared['pain001_xml'] !== $xml || $prepared['payload_sha256'] !== hash('sha256', $xml)) {
  throw new RuntimeException('Incorrect prepared payment');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_bank_submission_intents')->fetchColumn() !== 1) {
  throw new RuntimeException('Reservation missing');
}
try {
  OfficePaymentSubmissionPreparation::prepare($payload, 3, $allow, $unchanged, $mandate, $artifacts, $submissions, $resolveKey);
  throw new RuntimeException('Duplicate submission accepted');
} catch (DomainException $expected) {}
echo "OfficePaymentSubmissionPreparationTest OK\n";
