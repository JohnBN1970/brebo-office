<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinancePresentationView.php';
$html = \Brebo\Office\Finance\OfficeFinancePresentationView::render();
foreach (['PRESENTATIEMODUS ACTIEF', '€ ••••••', 'Bankzaken', 'Facturen'] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Presentation placeholder missing');
    }
}
foreach (['<iframe', '<form', 'href=', 'fetch(', 'src=', 'rekeningnummer', 'IBAN'] as $forbidden) {
    if (stripos($html, $forbidden) !== false) {
        throw new RuntimeException('Presentation view includes interactive or account data');
    }
}
echo "OfficeFinancePresentationViewTest OK\n";
