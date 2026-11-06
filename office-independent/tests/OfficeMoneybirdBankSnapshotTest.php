<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';
use Brebo\Office\Finance\OfficeMoneybirdBankSnapshot;

$payload = ['status'=>'ok','provider'=>'moneybird','bank'=>[
  'balances_available'=>false,'period'=>'this_month',
  'accounts'=>[['id'=>'502980359672039351','name'=>'Bank','identifier'=>'NL00TEST','currency'=>'EUR','balance'=>'9999']],
  'transactions'=>[['id'=>'502980365165528473','financial_account_id'=>'502980359672039351','amount'=>'-12.3400','date'=>'2026-11-06','message'=>'<script>test</script>','payments'=>[['invoice_id'=>'private-invoice']]]],
]];
$snapshot = OfficeMoneybirdBankSnapshot::fromIntegrationApi($payload);
if ($snapshot['period'] !== 'this_month' || $snapshot['balances_available'] !== false
    || count($snapshot['transactions']) !== 1 || $snapshot['transactions'][0]['amount'] !== '-12.3400'
    || $snapshot['accounts'][0]['id'] !== '502980359672039351'
    || $snapshot['transactions'][0]['matching_status'] !== 'unverified') {
  throw new RuntimeException('Bank contract or precision lost');
}
if (str_contains(json_encode($snapshot, JSON_THROW_ON_ERROR), 'private-invoice')
    || str_contains(json_encode($snapshot, JSON_THROW_ON_ERROR), '9999')) throw new RuntimeException('Invoice or balance leaked');
foreach (['missing', 'this_year', '<script>period</script>'] as $period) {
  $invalid = $payload;
  if ($period === 'missing') unset($invalid['bank']['period']);
  else $invalid['bank']['period'] = $period;
  try {
    OfficeMoneybirdBankSnapshot::fromIntegrationApi($invalid);
  } catch (InvalidArgumentException $expected) { continue; }
  throw new RuntimeException('Unverified period accepted');
}
$invalid = $payload;
$invalid['bank']['balances_available'] = true;
try {
  OfficeMoneybirdBankSnapshot::fromIntegrationApi($invalid);
  throw new RuntimeException('Balance claim accepted');
} catch (InvalidArgumentException $expected) {}
echo "OFFICE_MONEYBIRD_BANK_SNAPSHOT_OK\n";
