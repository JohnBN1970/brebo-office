<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Finance/OfficeMoneyDisplay.php';
use Brebo\Office\Finance\OfficeMoneyDisplay;
$checks=[
 [125050,'EUR','nl-NL','€ 1.250,50'],
 [-125050,'EUR','nl-NL','-€ 1.250,50'],
 [123456,'GBP','en-GB','£ 1,234.56'],
 [0,'USD','nl-NL','$ 0,00'],
];
foreach($checks as [$c,$currency,$locale,$expected]){
 if(OfficeMoneyDisplay::formatCents($c,$currency,$locale)!==$expected)throw new RuntimeException('Currency display mismatch');
}
echo "OFFICE_MONEY_DISPLAY_OK\n";
