<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceNumber.php';
require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceDraftInput.php';

use Brebo\Office\Finance\OfficeSalesInvoiceNumber;
use Brebo\Office\Finance\OfficeSalesInvoiceDraftInput;

function assertRejects(callable $action): void
{
    try {
        $action();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException('Ongeldige invoer niet afgewezen');
}

assert(OfficeSalesInvoiceNumber::format(2026, 1) === 'VF-2026-000001');
assert(OfficeSalesInvoiceNumber::format(2026, 999999) === 'VF-2026-999999');
assertRejects(fn () => OfficeSalesInvoiceNumber::format(2026, 0));
assertRejects(fn () => OfficeSalesInvoiceNumber::format(2026, 1000000));

$input = OfficeSalesInvoiceDraftInput::validate([
    'debtor_id' => 15,
    'invoice_date' => '2026-10-31',
    'due_date' => '2026-11-30',
    'description' => 'Werkzaamheden',
]);
if ($input['debtor_id'] !== 15 || $input['project_id'] !== null) {
    throw new RuntimeException('Zelfstandige factuur moet zonder project kunnen');
}
assertRejects(fn () => OfficeSalesInvoiceDraftInput::validate([
    'debtor_id' => 0, 'invoice_date' => '2026-10-31', 'due_date' => '2026-11-30',
]));
assertRejects(fn () => OfficeSalesInvoiceDraftInput::validate([
    'debtor_id' => 15, 'invoice_date' => '2026-02-30', 'due_date' => '2026-11-30',
]));
assertRejects(fn () => OfficeSalesInvoiceDraftInput::validate([
    'debtor_id' => 15, 'invoice_date' => '2026-11-30', 'due_date' => '2026-10-31',
]));
echo "Office-native verkoopfactuurcontract: OK\n";
