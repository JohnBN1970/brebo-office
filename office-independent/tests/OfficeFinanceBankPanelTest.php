<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceBankPanel.php';
use Brebo\Office\Finance\OfficeFinanceBankPanel;
$empty = OfficeFinanceBankPanel::render(null);
if (!str_contains($empty, 'nog niet verbonden')) throw new RuntimeException('Missing offline status');
$panel = OfficeFinanceBankPanel::render([
 'provider'=>'moneybird','balances_available'=>false,'period'=>'this_month',
 'accounts'=>[['name'=>'Bank','identifier'=>'NL00TEST','currency'=>'EUR']],
 'transactions'=>[['booking_date'=>'2026-11-05','description'=>'<script>alert(1)</script>','amount'=>'5.00','currency'=>'EUR']],
]);
if (str_contains($panel,'<script>') || !str_contains($panel,'&lt;script&gt;')) throw new RuntimeException('Unsafe output');
if (!str_contains($panel,'Niet beschikbaar') || !str_contains($panel,'Niet geverifieerd')) throw new RuntimeException('Unsafe balance/payment claim');
if (!str_contains($panel, 'deze maand volgens Moneybird') || !str_contains($panel, 'Geen volledig historisch overzicht')) throw new RuntimeException('Missing bank period');
foreach ([null, 'this_year', '<script>period</script>'] as $period) {
  try {
    OfficeFinanceBankPanel::render(['provider'=>'moneybird','balances_available'=>false,'period'=>$period]);
  } catch (InvalidArgumentException $expected) { continue; }
  throw new RuntimeException('Unverified period rendered');
}
echo "OFFICE_FINANCE_BANK_PANEL_OK\n";


$bookedPanel=OfficeFinanceBankPanel::render(['provider'=>'moneybird','balances_available'=>false,'period'=>'this_month',
 'accounts'=>[['id'=>'1','name'=>'Bank','identifier'=>'TEST','currency'=>'EUR'],['id'=>'2','name'=>'Missing','currency'=>'EUR'],['id'=>'3','name'=>'Zero','currency'=>'EUR']],
 'transactions'=>[], 'booked_balances'=>['source'=>'moneybird_bank_balance_report','period'=>'this_month','retrieved_at'=>'2026-11-06T12:00:00.000Z',
 'accounts'=>[['financial_account_id'=>'1','amount'=>'-12345678901234567890.1200'],['financial_account_id'=>'3','amount'=>'0']]]]);
foreach (['-12.345.678.901.234.567.890,1200','0,00','Niet aangeleverd','Boekhoudkundig','Geen door de bank bevestigd actueel of beschikbaar saldo','06-11-2026 13:00 CET'] as $required) {
  if (!str_contains($bookedPanel,$required)) throw new RuntimeException('Booked balance labeling or precision missing');
}
if (substr_count($bookedPanel,'0,00')!==1) throw new RuntimeException('Missing balance became zero');
echo "OFFICE_BOOKED_BALANCE_PANEL_OK\n";
