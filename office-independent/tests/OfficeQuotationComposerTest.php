<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Quotation/OfficeQuotationComposer.php';

use Brebo\Office\Quotation\OfficeQuotationComposer;

function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

check(count(OfficeQuotationComposer::sections('project')) === 9, 'Project template');
check(in_array('deliverables', OfficeQuotationComposer::sections('mjop'), true), 'MJOP template');
check(in_array('rates', OfficeQuotationComposer::sections('regie'), true), 'Regie template');
$terms = OfficeQuotationComposer::instalments(10001, [
    ['percentage' => 30, 'trigger' => 'Opdracht'],
    ['percentage' => 20, 'trigger' => 'Start'],
    ['percentage' => 45, 'trigger' => 'Voortgang'],
    ['percentage' => 5, 'trigger' => 'Oplevering'],
]);
check(array_sum(array_column($terms, 'amount_cents')) === 10001, 'Amounts reconcile');
check($terms[0]['amount_cents'] === 3000, 'First term');
$draft = OfficeQuotationComposer::draft('compact', ['sections' => ['intro' => 'Introductie']]);
check($draft['status'] === 'draft' && $draft['approved'] === false, 'Never auto-approve');
check($draft['sections'][0]['text'] === 'Introductie', 'Preserve supplied text');
$rejected = false;
try { OfficeQuotationComposer::instalments(10000, [['percentage' => 90, 'trigger' => 'Start']]); }
catch (InvalidArgumentException $e) { $rejected = true; }
check($rejected, 'Reject totals other than 100');
echo "OfficeQuotationComposerTest OK\n";
