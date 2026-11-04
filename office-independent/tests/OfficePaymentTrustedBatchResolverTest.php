<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentTrustedBatchResolver.php';
use Brebo\Office\Finance\OfficePaymentTrustedBatchResolver;
$calls = 0;
$resolver = new OfficePaymentTrustedBatchResolver(static function(int $id) use (&$calls): array {
  $calls++;
  return ['batch'=>['id'=>$id,'status'=>'released','created_by'=>3,'released_by'=>4,'controller_verdict'=>'green','control_sum'=>'50.00','sealed_hash'=>str_repeat('a',64)],
    'sealed_hash'=>str_repeat('a',64),'items'=>[['invoice_id'=>7]]];
});
foreach ([0, -1, '1 OR 1=1', ['id'=>1], '01', '', null] as $bad) {
  try { $resolver->resolve($bad); throw new RuntimeException('Untrusted batch ID accepted'); }
  catch (DomainException $expected) {}
}
if ($calls !== 0) throw new RuntimeException('Untrusted batch reached canonical loader');
if ($resolver->resolve('12')['batch']['id'] !== 12 || $calls !== 1) {
  throw new RuntimeException('Trusted batch lookup failed');
}
$wrong = new OfficePaymentTrustedBatchResolver(static fn(int $id): array =>
  ['batch'=>['id'=>$id+1,'status'=>'released','sealed_hash'=>str_repeat('a',64)],
   'sealed_hash'=>str_repeat('a',64),'items'=>[['invoice_id'=>7]]]);
try { $wrong->resolve(12); throw new RuntimeException('Mismatched batch accepted'); }
catch (DomainException $expected) {}
$unreleased = new OfficePaymentTrustedBatchResolver(static fn(int $id): array =>
  ['batch'=>['id'=>$id,'status'=>'released','sealed_hash'=>str_repeat('a',64),
    'created_by'=>3,'released_by'=>4,'controller_verdict'=>'red','control_sum'=>'50.00'],
   'sealed_hash'=>str_repeat('a',64),'items'=>[['invoice_id'=>7]]]);
try { $unreleased->resolve(12); throw new RuntimeException('Red control verdict accepted'); }
catch (DomainException $expected) {}
echo "OfficePaymentTrustedBatchResolverTest OK\n";
