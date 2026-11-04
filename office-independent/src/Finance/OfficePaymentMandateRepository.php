<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Read-only mandate lookup; absence, disabled or expired means no authority. */
final class OfficePaymentMandateRepository {
  public function __construct(private readonly \PDO $pdo) {}

  public function forOperator(int $operatorId): array {
    if ($operatorId < 1) throw new \DomainException('Valid operator required');
    $statement = $this->pdo->prepare(
      'SELECT enabled, mode, limit_cents, dual_above_cents
       FROM office_payment_mandates
       WHERE operator_id = :operator AND enabled = 1
         AND valid_from <= UTC_TIMESTAMP()
         AND (valid_until IS NULL OR valid_until > UTC_TIMESTAMP())
       LIMIT 1'
    );
    $statement->execute(['operator' => $operatorId]);
    $row = $statement->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new \DomainException('No active payment mandate');
    return [
      'enabled' => true,
      'mode' => (string)$row['mode'],
      'limit_eur' => self::euros((string)$row['limit_cents']),
      'dual_above_eur' => $row['dual_above_cents'] === null ? null : self::euros((string)$row['dual_above_cents']),
    ];
  }

  private static function euros(string $cents): string {
    if (!ctype_digit($cents) || strlen($cents) > 16) throw new \DomainException('Invalid mandate limit');
    $value = (int)$cents;
    return intdiv($value, 100) . '.' . str_pad((string)($value % 100), 2, '0', STR_PAD_LEFT);
  }
}
