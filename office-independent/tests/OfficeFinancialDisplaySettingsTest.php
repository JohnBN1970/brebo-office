<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Finance/OfficeFinancialDisplaySettings.php';
use Brebo\Office\Finance\OfficeFinancialDisplaySettings;
foreach ([['EUR','nl-NL'],['USD','en-GB'],['GBP','nl-NL']] as [$currency,$locale]) {
    OfficeFinancialDisplaySettings::validate($currency,$locale);
}
foreach ([['JPY','nl-NL'],['EUR','fr-FR'],['','nl-NL']] as [$currency,$locale]) {
    try { OfficeFinancialDisplaySettings::validate($currency,$locale); throw new RuntimeException('Expected validation rejection'); }
    catch (DomainException $expected) {}
}
echo "OFFICE_FINANCIAL_SETTINGS_OK\n";
