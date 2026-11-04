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
  foreach (['office_payment_mandates','office_sepa_batch_artifacts',
    'office_bank_submission_intents','brebo_finance_payment_batch',
    'brebo_finance_payment_batch_item'] as $table) {
    $db->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY)');
  }
  $state = OfficePaymentReadiness::inspect($db, true, true);
  if (!$state['ready']) throw new RuntimeException('Complete readiness not recognized');
  if (OfficePaymentReadiness::inspect($db, true, false)['ready']) {
    throw new RuntimeException('Unauthorized operator recognized as ready');
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
