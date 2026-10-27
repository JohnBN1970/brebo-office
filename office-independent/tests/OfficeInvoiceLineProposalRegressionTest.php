<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceLineProposal.php';

use Brebo\Office\Finance\OfficeInvoiceLineProposal;

function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
}

$normal = OfficeInvoiceLineProposal::fromText("Kozijnprofiel 2 st 12,50 25,00\nMontagekit 3 4,00 12,00");
check(count($normal) === 2, 'Mixed unit and unitless lines should both be recognized');
check($normal[0]['quantity'] === '2' && $normal[0]['line_total'] === '25,00', 'Unit row mismatch');
check($normal[1]['quantity'] === '3' && $normal[1]['unit'] === '', 'Unitless row mismatch');
check($normal[0]['vat_rate'] === '' && $normal[1]['vat_rate'] === '', 'VAT must not be inferred');

$invalid = OfficeInvoiceLineProposal::fromText("Kozijnprofiel 2 st 12,50 26,00");
check($invalid === [], 'Inconsistent amounts must not be proposed');

$proge = OfficeInvoiceLineProposal::fromText("AfleveradresB R E B O B o u w e n A d v ie s B .V .m1PVC hoek 40x10x2 mm zwart in\nlengte 200 cm\n..../ 20048 214,082,2396,00PVC40101");
check(count($proge) === 1, 'Multiline product description must be recognized');
check($proge[0]['description'] === 'PVC hoek 40x10x2 mm zwart in lengte 200 cm', 'Description mismatch');
check($proge[0]['quantity'] === '' && $proge[0]['unit_price'] === '' && $proge[0]['vat_rate'] === '', 'Merged PDF numbers must not be guessed');

echo "INVOICE_LINE_PROPOSAL_REGRESSION_OK\n";
