<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure reconciliation of independently sourced quantities.
 * All hours are integer seconds. Never mutate OnSite presence or invoice data.
 */
final class OfficeExternalHoursReconciliationPolicy
{
    public static function compare(
        int $presentSeconds,
        int $claimedSeconds,
        ?int $invoicedSeconds,
        ?int $approvedPayableSeconds = null
    ): array {
        foreach ([$presentSeconds,$claimedSeconds,$invoicedSeconds,$approvedPayableSeconds] as $seconds) {
            if ($seconds !== null && ($seconds < 0 || $seconds > 86400)) {
                throw new \DomainException('Invalid hours quantity');
            }
        }
        if ($approvedPayableSeconds !== null && $approvedPayableSeconds > $claimedSeconds) {
            throw new \DomainException('Payable hours exceed external claim');
        }
        $presenceVariance=$claimedSeconds-$presentSeconds;
        $invoiceVariance=$invoicedSeconds === null ? null : $invoicedSeconds-$claimedSeconds;
        $status=$invoicedSeconds === null ? 'awaiting_invoice'
            : ($presenceVariance !== 0 || $invoiceVariance !== 0 ? 'review_required' : 'matched');
        if ($approvedPayableSeconds !== null) {
            $status='reviewed';
        }
        return [
            'present_seconds'=>$presentSeconds,
            'claimed_seconds'=>$claimedSeconds,
            'invoiced_seconds'=>$invoicedSeconds,
            'approved_payable_seconds'=>$approvedPayableSeconds,
            'presence_variance_seconds'=>$presenceVariance,
            'invoice_variance_seconds'=>$invoiceVariance,
            'disputed_seconds'=>$approvedPayableSeconds === null ? null : $claimedSeconds-$approvedPayableSeconds,
            'status'=>$status,
        ];
    }
}
