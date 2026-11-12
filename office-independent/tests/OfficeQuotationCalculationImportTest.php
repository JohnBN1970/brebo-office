<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalculationImport.php';

use Brebo\Office\Quotation\OfficeQuotationCalculationImport;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$snapshot = [
    'calculation_id' => 25,
    'version' => 3,
    'sales_lines' => [
        ['description' => 'Kozijnen leveren', 'quantity' => 2, 'unit' => 'st', 'amount_ex_vat_cents' => 120000],
        ['description' => 'Montage', 'quantity' => 8, 'unit' => 'uur', 'amount_ex_vat_cents' => 64000],
    ],
];
$result = OfficeQuotationCalculationImport::prepare($snapshot);
$assert($result['total_ex_vat_cents'] === 184000, 'Incorrect total');
$assert($result['calculation_id'] === 25 && $result['calculation_version'] === 3, 'Source identity lost');
$assert(count($result['sales_lines']) === 2, 'Sales lines missing');
$assert($result['sales_lines'][0]['unit_sales_price_ex_vat'] === 600.0, 'Incorrect unit selling price');
$assert($result['sales_lines'][1]['unit_sales_price_ex_vat'] === 80.0, 'Incorrect hourly selling price');
$assert(!array_key_exists('labour', $result['sales_lines'][0]), 'Internal labour cost exposed');
$assert($result['source_hash'] === OfficeQuotationCalculationImport::prepare($snapshot)['source_hash'], 'Non-deterministic hash');
$assert(!array_key_exists('cost_price', $result), 'Internal cost exposed');
$invalid = $snapshot;
$invalid['sales_lines'][0]['amount_ex_vat_cents'] = -1;
try {
    OfficeQuotationCalculationImport::prepare($invalid);
    throw new RuntimeException('Negative price accepted');
} catch (InvalidArgumentException) {}
$invalid = $snapshot;
unset($invalid['version']);
try {
    OfficeQuotationCalculationImport::prepare($invalid);
    throw new RuntimeException('Unversioned calculation accepted');
} catch (InvalidArgumentException) {}
$invalid = $snapshot;
$invalid['sales_lines'][0]['quantity'] = '1e309';
try {
    OfficeQuotationCalculationImport::prepare($invalid);
    throw new RuntimeException('Non-finite quantity accepted');
} catch (InvalidArgumentException) {}
echo "OFFERTSTUDIO_CALC_IMPORT_TEST_OK\n";
