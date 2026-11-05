<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankApiClient.php';
use Brebo\Office\Finance\OfficeMoneybirdBankApiClient;
putenv('OFFICE_MONEYBIRD_BANK_API_URL=https://example.invalid/v1/accounting/bank-data');
putenv('OFFICE_INTEGRATION_SHARED_SECRET=invalid-test-secret-1234567890');
try {
  OfficeMoneybirdBankApiClient::fetch();
  throw new RuntimeException('Client must reject unapproved endpoint');
} catch (RuntimeException $e) {
  if ($e->getMessage() !== 'Bank integration not configured') throw $e;
}
echo "OFFICE_MONEYBIRD_BANK_API_CLIENT_FAIL_CLOSED_OK\n";
