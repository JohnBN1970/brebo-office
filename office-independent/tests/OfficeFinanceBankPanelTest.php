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

