<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficePaymentBatchReleaseGate.php';
use Brebo\Office\Finance\OfficePaymentBatchReleaseGate as Gate;
$batch = ['batch_number'=>'TEST-001','execution_date'=>'2099-01-01','currency'=>'EUR',
  'status'=>'reviewed','controller_verdict'=>'green','created_by'=>10,
  'reviewed_by'=>11,'reviewed'=>100,'item_count'=>1];
$items = [[
  'release_id'=>1,'invoice_id'=>1,'instruction_type'=>'regular','amount'=>'12.00',
  'currency'=>'EUR','creditor_name'=>'Test','creditor_iban'=>'NL91ABNA0417164300',
  'creditor_bic'=>null,'recipient_hash'=>str_repeat('a',64),
  'end_to_end_id'=>'TEST-1','remittance_information'=>'Test',
  'status'=>'prepared'
]];
$batch['payload_hash']=Gate::payloadHash($batch,$items);
$count=0;
$ok=Gate::assertReleasable($batch,$items,12,'Approved by independent operator');
if ($ok!==$batch['payload_hash']) throw new RuntimeException('Valid seal mismatch');
$count++;
$reject=static function(array $b,array $i,int $operator=12,string $note='Approved') use (&$count): void {
  try { Gate::assertReleasable($b,$i,$operator,$note); }
  catch (DomainException $e) { $count++; return; }
  throw new RuntimeException('Unsafe release accepted');
};
$reject($batch,$items,10);
$reject($batch,$items,11);
$reject($batch,$items,0);
$reject($batch,$items,12,'');
foreach ([
  ['status','draft'],['controller_verdict','red'],['created_by',0],
  ['reviewed_by',0],['reviewed',0],['item_count',2],['currency','USD'],
  ['payload_hash',str_repeat('0',64)]
] as [$key,$value]) {
  $changed=$batch; $changed[$key]=$value; $reject($changed,$items);
}
foreach ([
  ['amount','13.00'],['creditor_iban','NL00FAKE0000000000'],
  ['recipient_hash',str_repeat('b',64)],['status','released'],
  ['instruction_type','g_account'],['currency','USD']
] as [$key,$value]) {
  $changed=$items; $changed[0][$key]=$value; $reject($batch,$changed);
}
$reject($batch,[]);
echo "OfficePaymentBatchReleaseGateTest OK ($count checks)\n";
