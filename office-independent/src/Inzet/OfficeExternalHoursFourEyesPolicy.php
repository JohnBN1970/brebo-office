<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Decision policy only: caller must authenticate reviewers, check project permissions,
 * persist immutable decisions and verify the underlying source documents.
 * Never mutates OnSite presence, source claims or invoices.
 */
final class OfficeExternalHoursFourEyesPolicy
{
    public static function approve(
        int $claimedSeconds,
        int $presentSeconds,
        ?int $invoicedSeconds,
        int $payableSeconds,
        string $firstReviewer,
        string $secondReviewer,
        string $reason,
        string $sourceLineSha256
    ): array {
        foreach ([$claimedSeconds,$presentSeconds,$invoicedSeconds,$payableSeconds] as $seconds) {
            if ($seconds !== null && ($seconds < 0 || $seconds > 86400)) {
                throw new \DomainException('Invalid external hours quantity');
            }
        }
        if ($claimedSeconds < 1 || $payableSeconds > $claimedSeconds) {
            throw new \DomainException('Payable hours exceed claim or claim empty');
        }
        $firstReviewer=trim($firstReviewer);
        $secondReviewer=trim($secondReviewer);
        $reason=trim($reason);
        if ($firstReviewer==='' || $secondReviewer==='' || strlen($firstReviewer)>128
            || strlen($secondReviewer)>128 || hash_equals($firstReviewer,$secondReviewer)) {
            throw new \DomainException('Independent second reviewer required');
        }
        if (strlen($reason)<10 || strlen($reason)>2000) {
            throw new \DomainException('Documented approval reason required');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D',$sourceLineSha256)) {
            throw new \DomainException('Verified source line hash required');
        }
        if ($invoicedSeconds !== null && $payableSeconds > $invoicedSeconds) {
            throw new \DomainException('Payable hours exceed invoiced hours');
        }
        return [
            'status'=>'four_eyes_approved',
            'claimed_seconds'=>$claimedSeconds,
            'present_seconds'=>$presentSeconds,
            'invoiced_seconds'=>$invoicedSeconds,
            'approved_payable_seconds'=>$payableSeconds,
            'presence_variance_seconds'=>$claimedSeconds-$presentSeconds,
            'disputed_seconds'=>$claimedSeconds-$payableSeconds,
            'first_reviewer'=>$firstReviewer,
            'second_reviewer'=>$secondReviewer,
            'reason'=>$reason,
            'source_line_sha256'=>$sourceLineSha256,
        ];
    }
}
