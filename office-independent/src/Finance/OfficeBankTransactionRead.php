<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use PDO;

final class OfficeBankTransactionRead {
  public function __construct(private readonly PDO $db) {}

  public function forAccount(string $provider, string $accountId, int $limit = 100): array {
    if ($provider === '' || $accountId === '' || $limit < 1 || $limit > 500) {
      throw new \InvalidArgumentException('Invalid account filter');
    }
    $query = $this->db->prepare('SELECT transaction_id, booking_date, currency, amount_minor, description FROM office_bank_transactions WHERE provider = ? AND account_id = ? ORDER BY booking_date DESC, transaction_id DESC LIMIT ' . $limit);
    $query->execute([$provider, $accountId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
  }
}
