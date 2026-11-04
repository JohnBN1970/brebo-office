<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentReadiness.php';
use Brebo\Office\Finance\OfficePaymentReadiness;
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$old = getenv('OFFICE_BANK_PAYMENTS_ENABLED');
$oldProvider = getenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED');
try {
  putenv('OFFICE_BANK_PAYMENTS_ENABLED');
  $state = OfficePaymentReadiness::inspect($db, false, true);
  if ($state['ready'] || $state['checks']['canonical_batch_schema_present']) {
    throw new RuntimeException('Unverified schema allowed payment readiness');
  }
  putenv('OFFICE_BANK_PAYMENTS_ENABLED=enabled');
  putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED=enabled');
  $tables = [
    'office_payment_mandates'=>['operator_id','enabled','mode','limit_cents','dual_above_cents','valid_from','valid_until'],
    'office_payment_mandate_audit'=>['mandate_id','changed_by','changed_at','previous_values_json','new_values_json'],
    'office_sepa_batch_artifacts'=>['batch_id','payload_sha256','ciphertext','encryption_nonce','encryption_key_id','created_by','created_at'],
    'office_bank_submission_intents'=>['batch_id','idempotency_key','payload_sha256','status','bank_reference','requested_by','created_at','changed_at'],
    'brebo_finance_payment_batch'=>['status','batch_number','execution_date','currency','sealed_hash','item_count','created_by','released_by','controller_verdict','control_sum'],
    'brebo_finance_payment_batch_item'=>['batch_id','position','release_id','invoice_id','instruction_type','amount','currency','creditor_name','creditor_iban','creditor_bic','recipient_hash','end_to_end_id','remittance_information','status'],
  ];
  foreach ($tables as $table=>$columns) {
    $definitions = array_map(static fn(string $column): string => $column . ' TEXT', $columns);
    $db->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, ' . implode(', ', $definitions) . ')');
  }
  $db->exec('ALTER TABLE office_payment_mandates DROP COLUMN dual_above_cents');
  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Incomplete mandate schema recognized as ready');
  }
  $db->exec('ALTER TABLE office_payment_mandates ADD COLUMN dual_above_cents TEXT');
  $db->exec('ALTER TABLE office_bank_submission_intents DROP COLUMN requested_by');
  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Incomplete submission persistence schema recognized as ready');
  }
  $db->exec('ALTER TABLE office_bank_submission_intents ADD COLUMN requested_by TEXT');

  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Missing unique payment reservation keys recognized as ready');
  }
  $db->exec('CREATE UNIQUE INDEX uq_office_bank_submission_batch ON office_bank_submission_intents (batch_id)');
  $db->exec('CREATE UNIQUE INDEX uq_office_bank_submission_key ON office_bank_submission_intents (idempotency_key)');
  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Missing artifact and mandate unique constraints recognized as ready');
  }
  $db->exec('CREATE UNIQUE INDEX uq_office_sepa_artifact_batch ON office_sepa_batch_artifacts (batch_id)');
  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Missing mandate uniqueness recognized as ready');
  }
  $db->exec('CREATE UNIQUE INDEX uq_office_payment_mandate_operator ON office_payment_mandates (operator_id)');
  $state = OfficePaymentReadiness::inspect($db, true, true);
  if ($state['ready'] || $state['checks']['payment_column_types_verified']
    || $state['checks']['payment_transactional_tables_verified']) {
    throw new RuntimeException('SQLite test schema must not pass production MySQL type or transaction verification');
  }
  foreach (['mandate_schema_present','artifact_schema_present','submission_schema_present',
    'canonical_batch_schema_present','submission_uniqueness_verified',
    'artifact_uniqueness_verified','mandate_uniqueness_verified',
    'mandate_audit_schema_present'] as $check) {
    if (!$state['checks'][$check]) throw new RuntimeException('Complete SQLite structural schema rejected: ' . $check);
  }
  if (OfficePaymentReadiness::inspect($db, true, false)['ready']) {
    throw new RuntimeException('Unauthorized operator recognized as ready');
  }
  // Metadata inspection must fail closed when the PDO driver cannot be read.
  $failing = new class extends PDO {
    public function __construct() {}
    public function getAttribute(int $attribute): mixed {
      throw new RuntimeException('Metadata driver unavailable');
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
      throw new RuntimeException('Metadata query unavailable');
    }
  };
  $failed = OfficePaymentReadiness::inspect($failing, true, true);
  if ($failed['ready'] || $failed['checks']['payment_transactional_tables_verified']
    || $failed['checks']['payment_column_types_verified']) {
    throw new RuntimeException('Metadata failures must block payment readiness');
  }
  $db->exec('DROP TABLE brebo_finance_payment_batch_item');
  if (OfficePaymentReadiness::inspect($db, true, true)['ready']) {
    throw new RuntimeException('Missing batch items recognized as ready');
  }
} finally {
  if ($oldProvider === false) putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED');
  else putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED=' . $oldProvider);
  if ($old === false) putenv('OFFICE_BANK_PAYMENTS_ENABLED');
  else putenv('OFFICE_BANK_PAYMENTS_ENABLED=' . $old);
}
echo "OfficePaymentReadinessTest OK\n";
