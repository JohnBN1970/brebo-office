<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcGenerationService.php';

use Brebo\Office\Quotation\OfficeQuotationCalcGenerationService;

$service = new OfficeQuotationCalcGenerationService(new PDO('sqlite::memory:'));
if (OfficeQuotationCalcGenerationService::editorUrl(12) !== '/office-test/crm/offertes?edit=12') {
    throw new RuntimeException('Editor redirect mismatch');
}
$called = false;
$intent = [
    'action' => 'generate_quotation',
    'calculation_id' => '12',
    'calculation_version' => '2',
    'csrf' => str_repeat('a', 64),
];
try {
    $service->generate(0, $intent, static function () use (&$called) {
        $called = true;
        return [];
    });
    throw new RuntimeException('Unauthenticated request accepted');
} catch (DomainException) {}
if ($called) throw new RuntimeException('Resolver called for unauthenticated request');
try {
    $service->generate(5, $intent, static function (int $operatorId, int $calculationId, int $version) use (&$called) {
        $called = true;
        if ($operatorId !== 5 || $calculationId !== 12 || $version !== 2) {
            throw new RuntimeException('Incorrect resolver arguments');
        }
        return ['relation_id' => 10, 'title' => 'Test', 'sales_snapshot' => [
            'calculation_id' => 12, 'version' => 3, 'sales_lines' => [],
        ]];
    });
    throw new RuntimeException('Mismatched source revision accepted');
} catch (DomainException) {}
if (!$called) throw new RuntimeException('Resolver not called for authenticated request');
foreach ([
    ['calculation_id' => 99, 'calculation_version' => 2],
    ['calculation_id' => 12, 'calculation_version' => 99],
] as $mismatch) {
    try {
        $service->generate(5, $intent, static function () use ($mismatch): array {
            return [
                'relation_id' => 10,
                'opportunity_id' => 11,
                'title' => 'Test',
                'calculation_id' => $mismatch['calculation_id'],
                'calculation_version' => $mismatch['calculation_version'],
                'sales_snapshot' => [
                    'calculation_id' => 12,
                    'version' => 2,
                    'sales_lines' => [['description' => 'Montage', 'quantity' => 1, 'unit' => 'st', 'amount_ex_vat_cents' => 100]],
                ],
            ];
        });
        throw new RuntimeException('Inconsistent authorized Calc identity accepted');
    } catch (DomainException $e) {
        if ($e->getMessage() === 'Inconsistent authorized Calc identity accepted') throw $e;
    }
}
echo "OFFERTSTUDIO_CALC_GENERATION_BOUNDARY_OK\n";
