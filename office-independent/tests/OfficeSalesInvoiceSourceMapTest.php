<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceSourceMap.php';

use Brebo\Office\Finance\OfficeSalesInvoiceSourceMap as Map;

assert(Map::draftTables() === [
    'brebo_finance_sales_invoice_draft',
    'brebo_finance_sales_invoice_draft_line',
]);
assert(Map::releaseTables() === [
    'brebo_finance_sales_invoice_draft',
    'brebo_finance_sales_invoice_draft_line',
    'brebo_finance_sales_invoice_outbox',
]);
assert(Map::ISSUED === 'brebo_finance_sales_invoice');

echo "OfficeSalesInvoiceSourceMapTest: OK\n";
