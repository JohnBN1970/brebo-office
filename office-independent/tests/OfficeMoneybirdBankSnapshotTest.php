<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';
use Brebo\Office\Finance\OfficeMoneybirdBankSnapshot;
$data = OfficeMoneybirdBankSnapshot::fromIntegrationApi([
 'status'=>'ok','provider'=>'moneybird',
 'bank'=>['balances_available'=>false,
 'accounts'=>[['id'=>'a1','currency'=>'EUR']],
 'transactions'=>[
 ['id'=>'m1','financial_account_id'=>'a1','amount'=>'-15.00','invoice'=>['secret'=>'excluded']],
 ['id'=>'m1','financial_account_id'=>'a1','amount'=>'-15.00'],
 ['id'=>'m2','financial_account_id'=>'missing','amount'=>'12.00'],
 ]]]);
assert(count($data['transactions']) === 1);
assert($data['transactions'][0]['matching_status'] === 'unverified');
if (str_contains(json_encode($data, JSON_THROW_ON_ERROR), 'secret')) {
 throw new RuntimeException('Invoice data leaked');
}
echo "OFFICE_MONEYBIRD_BANK_SNAPSHOT_OK\n";
