<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeIncomingInvoiceRouting.php';

use Brebo\Office\Communication\OfficeIncomingInvoiceRouting as Routing;

$base = [
    'invoice_candidate' => true,
    'attachment_stored' => true,
    'invoice_number' => 'INV-100',
    'supplier_verified' => true,
    'amounts_verified' => true,
    'duplicate' => false,
    'verified_project_matches' => 1,
    'company_expense_verified' => false,
];
$assert = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$r = Routing::decide($base);
$assert($r['action'] === 'propose_project_link', 'Project invoice without purchase order');
$r = Routing::decide(array_replace($base, ['verified_project_matches' => 0, 'company_expense_verified' => true]));
$assert($r['action'] === 'propose_company_expense', 'General overhead without project');
$r = Routing::decide(array_replace($base, ['verified_project_matches' => 0]));
$assert($r['action'] === 'finance_review', 'Unknown allocation requires review');
$r = Routing::decide(array_replace($base, ['duplicate' => true]));
$assert(in_array('possible_duplicate', $r['reasons'], true), 'Duplicate requires review');
$r = Routing::decide(array_replace($base, ['verified_project_matches' => 2]));
$assert(in_array('ambiguous_project', $r['reasons'], true), 'Ambiguous project requires review');
$r = Routing::decide(['invoice_candidate' => false]);
$assert($r['classification'] === 'other_mail', 'Ordinary email is not a payable');
echo "Office incoming invoice routing: PASS\n";
