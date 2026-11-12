<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationRubricReview.php';

use Brebo\Office\Quotation\OfficeQuotationRubricReview;

$hash = str_repeat('a', 64);
$rubric = ['lines' => [['description' => 'Kozijn', 'quantity' => 2, 'unit' => 'st', 'amount_ex_vat_cents' => 10000]], 'review' => ['approved' => false]];
$checks = ['description_checked' => true, 'quantities_checked' => true, 'prices_checked' => true];
try {
    OfficeQuotationRubricReview::approve($rubric, ['description_checked' => true, 'quantities_checked' => true], $hash, 5);
    throw new RuntimeException('Missing price check accepted');
} catch (DomainException $expected) {}
$approved = OfficeQuotationRubricReview::approve($rubric, $checks, $hash, 5);
if (!OfficeQuotationRubricReview::allApproved([$approved], $hash)) throw new RuntimeException('Valid approval rejected');
if (OfficeQuotationRubricReview::allApproved([$approved], str_repeat('b', 64))) throw new RuntimeException('Stale Calc approval accepted');
if (OfficeQuotationRubricReview::allApproved([], $hash)) throw new RuntimeException('Empty rubric list accepted');
echo "OFFERTSTUDIO_RUBRIC_REVIEW_TEST_OK\n";
