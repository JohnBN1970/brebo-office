<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcLaunchContract.php';

use Brebo\Office\Quotation\OfficeQuotationCalcLaunchContract;

$valid = [
    'action' => 'generate_quotation',
    'calculation_id' => '42',
    'calculation_version' => '3',
    'csrf' => str_repeat('a', 64),
];
$intent = OfficeQuotationCalcLaunchContract::validateIntent($valid);
if ($intent !== ['calculation_id' => 42, 'calculation_version' => 3]) {
    throw new RuntimeException('Valid Calc intent was not normalized');
}
$reject = static function (array $payload): void {
    try {
        OfficeQuotationCalcLaunchContract::validateIntent($payload);
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException('Invalid Calc handoff intent was accepted');
};
$reject(array_merge($valid, ['amount_ex_vat_cents' => '100']));
$reject(array_merge($valid, ['action' => 'import_calculation']));
$reject(array_merge($valid, ['calculation_id' => '-1']));
$reject(array_merge($valid, ['calculation_version' => '0']));
$reject(array_merge($valid, ['csrf' => 'invalid']));
echo "OFFERTSTUDIO_CALC_LAUNCH_CONTRACT_OK\n";
