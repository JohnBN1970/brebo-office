<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcPostController.php';
use Brebo\Office\Quotation\OfficeQuotationCalcPostController;
$csrf = str_repeat('a', 64);
$html = OfficeQuotationCalcPostController::button(12, 3, $csrf, '/office-test/calculatie/offerte-genereren');
foreach (['method="post"', 'Offerte genereren', 'name="calculation_id" value="12"', 'name="calculation_version" value="3"', $csrf] as $fragment) {
    if (!str_contains($html, $fragment)) throw new RuntimeException('Missing quotation form fragment: ' . $fragment);
}
foreach ([
    [0, 3, $csrf, '/office-test/calculatie/offerte-genereren'],
    [12, 0, $csrf, '/office-test/calculatie/offerte-genereren'],
    [12, 3, 'bad', '/office-test/calculatie/offerte-genereren'],
    [12, 3, $csrf, 'https://example.org'],
] as $invalid) {
    try { OfficeQuotationCalcPostController::button(...$invalid); }
    catch (DomainException) { continue; }
    throw new RuntimeException('Unsafe quotation form accepted');
}
$source = file_get_contents(dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcPostController.php');
foreach (['hash_equals(', "method !== 'POST'", '->generate(', '::editorUrl('] as $fragment) {
    if (!str_contains($source, $fragment)) throw new RuntimeException('Missing POST guard: ' . $fragment);
}
echo "QUOTATION_CALC_POST_BOUNDARY=PASS\n";
