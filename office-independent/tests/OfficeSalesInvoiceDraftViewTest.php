<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceDraftView.php';

use Brebo\Office\Finance\OfficeSalesInvoiceDraftView;

$empty = OfficeSalesInvoiceDraftView::render([]);
if (!str_contains($empty, 'Geen toegankelijke verkoopfactuurconcepten')) {
    throw new RuntimeException('Empty state missing');
}

$html = OfficeSalesInvoiceDraftView::render([[
    'id' => 42,
    'draft_number' => '<script>alert(1)</script>',
    'office_project_id' => 7,
    'invoice_date' => '2026-10-31',
    'status' => 'draft',
]]);
if (str_contains($html, '<script>') || !str_contains($html, '&lt;script&gt;')) {
    throw new RuntimeException('Draft number was not escaped');
}
if (!str_contains($html, '2026-10-31') || !str_contains($html, '>7</td>')) {
    throw new RuntimeException('Draft row missing');
}

echo "OfficeSalesInvoiceDraftViewTest: OK\n";
