<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;
use DomainException;

require_once __DIR__ . '/OfficeBankTransactionNormalizer.php';

/**
 * Idempotent read-only bank feed import storage.
 * Caller must verify bank-provider authentication, tenant/account ownership and
 * access rights before importing. This class never initiates a bank payment.
 */
final class OfficeBankTransactionImport
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function import(array $transactions): array
    {
        if (count($transactions) > 500) {
            throw new DomainException('Bank import batch exceeds limit');
        }
        $normalized = [];
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                throw new DomainException('Invalid bank feed entry');
            }
            $normalized[] = OfficeBankTransactionNormalizer::normalize($transaction);
        }
        $inserted = 0;
        $duplicates = 0;
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare(
                'INSERT INTO office_bank_transactions (deduplication_key, provider, account_id, transaction_id, booking_date, currency, amount_minor, description) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $exists = $this->db->prepare('SELECT 1 FROM office_bank_transactions WHERE deduplication_key = ? LIMIT 1');
            foreach ($normalized as $transaction) {
                $exists->execute([$transaction['deduplication_key']]);
                if ($exists->fetchColumn() !== false) {
                    ++$duplicates;
                    continue;
                }
                $insert->execute([
                    $transaction['deduplication_key'],
                    $transaction['provider'],
                    $transaction['account_id'],
                    $transaction['transaction_id'],
                    $transaction['booking_date'],
                    $transaction['currency'],
                    $transaction['amount_minor'],
                    $transaction['description'],
                ]);
                ++$inserted;
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
        return ['inserted' => $inserted, 'duplicates' => $duplicates];
    }
}
