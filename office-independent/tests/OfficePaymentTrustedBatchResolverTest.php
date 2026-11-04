<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentCanonicalSeal.php';
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentTrustedBatchResolver.php';
use Brebo\Office\Finance\OfficePaymentTrustedBatchResolver;
$item = ['release_id'=>5,'invoice_id'=>9,'instruction_type'=>'regular',
  'amount'=>'50.00','currency'=>'EUR','creditor_name'=>'Supplier',
  'creditor_iban'=>'NL91ABNA0417164300','creditor_bic'=>null,
  'recipient_hash'=>'recipient','end_to_end_id'=>'invoice-9',
  'remittance_information'=>'Invoice 9','status'=>'released'];
$makeBatch = static function(int $id) use ($item): array {
  $batch = ['id'=>$id,'status'=>'released','created_by'=>3,'released_by'=>4,
    'controller_verdict'=>'green','control_sum'=>'50.00',
    'batch_number'=>'BRB-27','execution_date'=>'2026-11-10','currency'=>'EUR'];
  $sealed = $item; unset($sealed['status']);
  $payload = ['batch_number'=>$batch['batch_number'],'execution_date'=>$batch['execution_date'],
    'currency'=>$batch['currency'],'items'=>[$sealed]];
  $batch['sealed_hash'] = hash('sha256', json_encode($payload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
  return ['batch'=>$batch,'items'=>[$item],'sealed_hash'=>$batch['sealed_hash']];
};
$calls = 0;
$resolver = new OfficePaymentTrustedBatchResolver(static function(int $id) use (&$calls,$makeBatch): array {
  $calls++;
  return $makeBatch($id);
});
foreach ([0, -1, '1 OR 1=1', ['id'=>1], '01', '', null] as $bad) {
  try { $resolver->resolve($bad); throw new RuntimeException('Untrusted batch ID accepted'); }
  catch (DomainException $expected) {}
}
if ($calls !== 0) throw new RuntimeException('Untrusted batch reached canonical loader');
if ($resolver->resolve('12')['batch']['id'] !== 12 || $calls !== 1) {
  throw new RuntimeException('Trusted batch lookup failed');
}
$wrong = new OfficePaymentTrustedBatchResolver(static function(int $id) use ($makeBatch): array {
  $data = $makeBatch($id); $data['batch']['id']++; return $data;
});
try { $wrong->resolve(12); throw new RuntimeException('Mismatched batch accepted'); }
catch (DomainException $expected) {}
$unreleased = new OfficePaymentTrustedBatchResolver(static function(int $id) use ($makeBatch): array {
  $data = $makeBatch($id); $data['batch']['controller_verdict']='red'; return $data;
});
try { $unreleased->resolve(12); throw new RuntimeException('Red control verdict accepted'); }
catch (DomainException $expected) {}
$tampered = new OfficePaymentTrustedBatchResolver(static function(int $id) use ($makeBatch): array {
  $data = $makeBatch($id); $data['items'][0]['creditor_iban']='NL02ABNA0123456789'; return $data;
});
try { $tampered->resolve(12); throw new RuntimeException('Changed IBAN accepted'); }
catch (DomainException $expected) {}
echo "OfficePaymentTrustedBatchResolverTest OK\n";
