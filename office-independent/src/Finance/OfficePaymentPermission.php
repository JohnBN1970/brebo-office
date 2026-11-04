<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Bank submission requires its own explicit Office permission, never Finance read/edit. */
final class OfficePaymentPermission {
  public function __construct(private readonly \PDO $pdo) {}

  public function canSubmit(int $operatorId): bool {
    if ($operatorId < 1) return false;
    $statement = $this->pdo->prepare(
      "SELECT 1 FROM office_module_permissions p
       INNER JOIN office_operator_accounts a ON a.id = p.operator_id
       WHERE p.operator_id = :operator AND p.module = 'finance'
         AND p.permission = 'bank_submit' AND a.active = 1 LIMIT 1"
    );
    $statement->execute(['operator'=>$operatorId]);
    return $statement->fetchColumn() !== false;
  }
}
