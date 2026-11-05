<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

use DomainException;

/**
 * Read-only bank transaction normalization boundary.
 * Does not connect to a provider, mutate ledger data, or initiate payments.
 * The provider adapter must verify authenticity and ownership before calling.
 */
final class OfficeBankTransactionNormalizer
{
    public static function normalize(array $transaction): array
    {
        foreach (['provider', 'account_id', 'transaction_id', 'booking_date', 'currency', 'amount_minor', 'description'] as $key) {
            if (!array_key_exists($key, $transaction)) {
                throw new DomainException('Missing bank transaction field: ' . $key);
            }
        }
        foreach (['provider', 'account_id', 'transaction_id', 'currency', 'description'] as $key) {
            if (!is_string($transaction[$key]) || trim($transaction[$key]) === '') {
                throw new DomainException('Invalid bank transaction field: ' . $key);
            }
        }
        if (!is_int($transaction['amount_minor'])) {
            throw new DomainException('Bank amount must be integer minor units');
        }
        $date = $transaction['booking_date'];
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            throw new DomainException('Invalid booking date');
        }
        $currency = $transaction['currency'];
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new DomainException('Invalid currency');
        }
        foreach (['provider', 'account_id', 'transaction_id'] as $key) {
            if (strlen($transaction[$key]) > 128) {
                throw new DomainException('Bank identifier too long');
            }
        }
        if (strlen($transaction['description']) > 2048) {
            throw new DomainException('Bank description too long');
        }
        return [
            'provider' => $transaction['provider'],
            'account_id' => $transaction['account_id'],
            'transaction_id' => $transaction['transaction_id'],
            'booking_date' => $date,
            'currency' => $currency,
            'amount_minor' => $transaction['amount_minor'],
            'description' => $transaction['description'],
            'deduplication_key' => hash('sha256', $transaction['provider'] . "\0" . $transaction['account_id'] . "\0" . $transaction['transaction_id']),
        ];
    }
}
