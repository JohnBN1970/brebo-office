<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

/**
 * Conservative intake routing for incoming invoice candidates.
 * This does not create a payable, approve an invoice or trigger payment.
 * An order reference is optional; evidence and verified project matching are not.
 */
final class OfficeIncomingInvoiceRouting
{
    /**
     * @param array<string, mixed> $evidence Trusted extraction and lookup results.
     * @return array{classification:string,action:string,reasons:list<string>}
     */
    public static function decide(array $evidence): array
    {
        $hasAttachment = ($evidence['attachment_stored'] ?? false) === true;
        $invoiceNumber = trim((string) ($evidence['invoice_number'] ?? ''));
        $supplierVerified = ($evidence['supplier_verified'] ?? false) === true;
        $duplicate = ($evidence['duplicate'] ?? false) === true;
        $projectMatches = (int) ($evidence['verified_project_matches'] ?? 0);
        $companyExpense = ($evidence['company_expense_verified'] ?? false) === true;
        $amountsVerified = ($evidence['amounts_verified'] ?? false) === true;
        $invoiceCandidate = ($evidence['invoice_candidate'] ?? false) === true;
        $reasons = [];

        if (!$invoiceCandidate) {
            return ['classification' => 'other_mail', 'action' => 'communication_review', 'reasons' => []];
        }
        if (!$hasAttachment) $reasons[] = 'attachment_missing';
        if ($invoiceNumber === '') $reasons[] = 'invoice_number_missing';
        if (!$supplierVerified) $reasons[] = 'supplier_unverified';
        if (!$amountsVerified) $reasons[] = 'amounts_unverified';
        if ($duplicate) $reasons[] = 'possible_duplicate';
        if ($projectMatches > 1) $reasons[] = 'ambiguous_project';
        if ($projectMatches === 0 && !$companyExpense) $reasons[] = 'allocation_missing';
        if ($projectMatches > 0 && $companyExpense) $reasons[] = 'conflicting_allocation';

        if ($reasons !== []) {
            return ['classification' => 'incoming_invoice', 'action' => 'finance_review', 'reasons' => $reasons];
        }
        return [
            'classification' => 'incoming_invoice',
            'action' => $projectMatches === 1 ? 'propose_project_link' : 'propose_company_expense',
            'reasons' => [],
        ];
    }
}
