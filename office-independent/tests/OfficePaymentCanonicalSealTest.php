<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentCanonicalSeal.php';
use Brebo\Office\Finance\OfficePaymentCanonicalSeal;
$item = ['release_id'=>5,'invoice_id'=>9,'instruction_type'=>'regular',
  'amount'=>'50.00','currency'=>'EUR','creditor_name'=>'Supplier',
  'creditor_iban'=>'NL91ABNA0417164300','creditor_bic'=>null,
  'recipient_hash'=>'recipient','end_to_end_id'=>'invoice-9',
  'remittance_information'=>'Invoice 9'];
$batch = ['batch_number'=>'BRB-27','execution_date'=>'2026-11-10','currency'=>'EUR'];
$payload = ['batch_number'=>$batch['batch_number'],'execution_date'=>$batch['execution_date'],
  'currency'=>$batch['currency'],'items'=>[$item]];
$batch['sealed_hash'] = hash('sha256', json_encode($payload,
  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
if (OfficePaymentCanonicalSeal::verify($batch, [$item]) !== $batch['sealed_hash']) {
  throw new RuntimeException('Valid seal rejected');
}
$changed = $item; $changed['creditor_iban'] = 'NL02ABNA0123456789';
try { OfficePaymentCanonicalSeal::verify($batch, [$changed]); throw new RuntimeException('Tampered IBAN accepted'); }
catch (DomainException $expected) {}
$changed = $item; unset($changed['creditor_name']);
try { OfficePaymentCanonicalSeal::verify($batch, [$changed]); throw new RuntimeException('Missing sealed field accepted'); }
catch (DomainException $expected) {}
echo "OfficePaymentCanonicalSealTest OK\n";
