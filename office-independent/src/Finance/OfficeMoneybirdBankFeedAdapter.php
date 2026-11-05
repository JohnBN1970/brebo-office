<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

use DomainException;

require_once __DIR__ . '/OfficeBankTransactionNormalizer.php';

/**
 * Maps a verified Moneybird read-only bank feed to Office's canonical format.
 * The caller must authenticate the feed and authorize each financial account.
 * Currency is supplied by the verified Moneybird financial-account metadata.
 */
final class OfficeMoneybirdBankFeedAdapter
{
    public static function normalize(array $mutation, string $currency): array
    {
        foreach (['id', 'financial_account_id', 'date', 'amount'] as $field) {
            if (!isset($mutation[$field]) || !is_string($mutation[$field]) || trim($mutation[$field]) === '') {
                throw new DomainException('Invalid Moneybird bank mutation field: ' . $field);
            }
        }
        if (!preg_match('/^-?(?:0|[1-9][0-9]*)\.[0-9]{1,4}$/D', $mutation['amount'])) {
            throw new DomainException('Invalid Moneybird bank amount');
        }
        [$whole, $fraction] = explode('.', ltrim($mutation['amount'], '-'), 2);
        if (strlen($fraction) > 2 && (int)substr($fraction, 2) !== 0) {
            throw new DomainException('Moneybird bank amount has sub-cent precision');
        }
        if (strlen($whole) > 14) {
            throw new DomainException('Moneybird bank amount out of range');
        }
        $minor = ((int)$whole * 100) + (int)substr(str_pad($fraction, 2, '0'), 0, 2);
        if (str_starts_with($mutation['amount'], '-')) {
            $minor = -$minor;
        }
        return OfficeBankTransactionNormalizer::normalize([
            'provider' => 'moneybird',
            'account_id' => $mutation['financial_account_id'],
            'transaction_id' => $mutation['id'],
            'booking_date' => $mutation['date'],
            'currency' => $currency,
            'amount_minor' => $minor,
            'description' => (is_string($mutation['description'] ?? null) && trim($mutation['description']) !== '')
                ? $mutation['description'] : 'Moneybird bankmutatie ' . $mutation['id'],
        ]);
    }
}
