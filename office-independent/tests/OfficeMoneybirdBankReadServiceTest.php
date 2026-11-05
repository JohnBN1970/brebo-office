<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankReadService.php';
use Brebo\Office\Finance\OfficeMoneybirdBankReadService;
$reads = 0;
$service = new OfficeMoneybirdBankReadService();
$result = $service->read(static function () use (&$reads): array {
  $reads++;
  return [
    'status'=>'ok','provider'=>'moneybird',
    'bank'=>[
      'balances_available'=>false,
      'accounts'=>[['id'=>'a','currency'=>'EUR']],
      'transactions'=>[['id'=>'t','financial_account_id'=>'a','amount'=>'1.25']],
    ],
  ];
});
if ($reads !== 1 || count($result['transactions']) !== 1) throw new RuntimeException('Read-only bank fetch failed');
if ($result['transactions'][0]['matching_status'] !== 'unverified') throw new RuntimeException('Unsafe payment confirmation');
echo "OFFICE_MONEYBIRD_BANK_READ_OK\n";
