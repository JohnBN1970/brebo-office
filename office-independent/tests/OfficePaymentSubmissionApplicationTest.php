<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficePaymentSubmissionApplication.php';
require_once $src . 'OfficeBankIntegrationApiClient.php';
require_once $src . 'OfficeSepaArtifactRepository.php';
require_once $src . 'OfficeBankSubmissionRepository.php';
use Brebo\Office\Finance\OfficePaymentSubmissionApplication as App;
use Brebo\Office\Finance\OfficeSepaArtifactRepository;
use Brebo\Office\Finance\OfficeBankSubmissionRepository;
use Brebo\Office\Finance\OfficeBankIntegrationApiClient;
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$client = new OfficeBankIntegrationApiClient('https://integration.example.test','secret','office',
  static function(): array { throw new RuntimeException('Bank must never be contacted'); });
try {
  App::submit([], 1, static fn(): bool => true, static fn(): bool => true,
    [], new OfficeSepaArtifactRepository($db), new OfficeBankSubmissionRepository($db),
    static fn(): string => '', $db, $client);
  throw new RuntimeException('Disabled payments were allowed');
} catch (DomainException $expected) {
  if ($expected->getMessage() !== 'Office bank payments disabled') throw $expected;
}

// Complete isolated payment handoff: no external bank network request.
foreach (['OfficeSepaArtifactCipher','OfficePaymentMandatePolicy','OfficePaymentSubmissionIdentity','OfficePaymentBatchSubmissionGuard','OfficeBankSubmissionIntent','OfficePaymentSubmissionPreparation','OfficePaymentSubmissionDispatch'] as $name) {
  require_once $src . $name . '.php';
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'));
$db->exec('CREATE TABLE office_sepa_batch_artifacts (
  batch_id INTEGER UNIQUE, payload_sha256 TEXT, ciphertext BLOB, encryption_nonce BLOB,
  encryption_key_id TEXT, created_by INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE office_bank_submission_intents (
  batch_id INTEGER UNIQUE, idempotency_key TEXT UNIQUE, payload_sha256 TEXT,
  status TEXT, requested_by INTEGER, created_at TEXT, changed_at TEXT, bank_reference TEXT)');
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$artifacts = new OfficeSepaArtifactRepository($db);
$submissions = new OfficeBankSubmissionRepository($db);
$artifacts->store(27, 3, $xml, $key, 'test-key');
$seal = str_repeat('a',64);
$batch = [
 'sealed_hash'=>$seal,
 'batch'=>['id'=>27, 'status'=>'released', 'sealed_hash'=>$seal, 'created_by'=>3,
 'released_by'=>3, 'controller_verdict'=>'green', 'control_sum'=>'50.00'],
 'items'=>[['status'=>'released', 'currency'=>'EUR', 'instruction_type'=>'regular',
 'invoice_id'=>9, 'recipient_hash'=>'recipient', 'creditor_iban'=>'NL91ABNA0417164300',
 'amount'=>'50.00', 'end_to_end_id'=>'invoice-9']]
];
$calls = 0;
$mockClient = new OfficeBankIntegrationApiClient('https://integration.example.test','secret','office',
  static function(string $url, string $path, string $body, string $id, string $secret) use (&$calls): array {
    $calls++;
    return ['status'=>'ok','provider'=>'abnamro','state'=>'submitted_for_authorization','bank_reference'=>'mock-27'];
  });
$args = [$batch, 3, static fn(int $actor): bool => $actor === 3,
  static fn(int $invoice, string $hash, int $actor): bool => $invoice === 9 && $hash === 'recipient' && $actor === 3,
  ['enabled'=>true, 'mode'=>'single', 'limit_eur'=>'100.00'],
  $artifacts, $submissions, static fn(string $id): string => $key, $db, $mockClient, true];
$result = App::submit(...$args);
if ($result['bank_reference'] !== 'mock-27' || $calls !== 1 || $db->inTransaction()) {
  throw new RuntimeException('Office payment handoff failed');
}
if ($db->query('SELECT status FROM office_bank_submission_intents WHERE batch_id=27')->fetchColumn() !== 'submitted_for_authorization') {
  throw new RuntimeException('Bank submission not persisted');
}
try { App::submit(...$args); throw new RuntimeException('Repeated batch accepted'); }
catch (DomainException $expected) {}
if ($calls !== 1) throw new RuntimeException('Bank called twice');

echo "OfficePaymentSubmissionApplicationTest OK\n";
