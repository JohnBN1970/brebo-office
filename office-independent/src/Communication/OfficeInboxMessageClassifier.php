<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

/**
 * Conservative inbox pre-classification. Never follows links, executes HTML,
 * authorizes forwarding or treats message content as trusted instructions.
 */
final class OfficeInboxMessageClassifier
{
    /** @return array{category:string,review_required:bool} */
    public function classify(string $subject, string $from, string $body): array
    {
        $text = mb_strtolower($subject . "\n" . $from . "\n" . $body, 'UTF-8');
        $zoho = str_contains($text, 'zoho');
        $verification = preg_match('/verif(?:icatie|y)|bevestig(?:ing|gen)?|verification|confirm|one.time|eenmalig|otp|code/i', $subject . "\n" . $body) === 1;
        if ($zoho && $verification) {
            return ['category' => 'verification_review', 'review_required' => true];
        }
        return ['category' => 'unclassified', 'review_required' => true];
    }
}
