<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use PDO;

/**
 * Read-only Office-native payment batch permission resolver.
 * Uses the existing office_module_permissions and active operator account.
 * No role-name shortcuts, no automatic grants and no payment side effects.
 */
final class OfficeFinanceBatchAccess {
  public function __construct(
    private readonly PDO $db,
    private readonly string $operatorId
  ) {}

  public function allows(string $permission): bool {
    $prefix = 'finance.';
    if (!str_starts_with($permission, $prefix)
      || !in_array($permission, OfficeFinanceBatchPermissionCatalog::permissions(), true)
      || trim($this->operatorId) === '') return false;

    $permissionKey = substr($permission, strlen($prefix));
    try {
      $query = $this->db->prepare(
        "SELECT 1 FROM office_module_permissions g
         INNER JOIN office_operator_accounts a ON a.id = g.operator_id
         WHERE g.operator_id = ? AND g.module = 'finance'
           AND g.permission = ? AND a.active = 1 LIMIT 1"
      );
      $query->execute([$this->operatorId, $permissionKey]);
      return $query->fetchColumn() !== false;
    } catch (\PDOException $exception) {
      return false;
    }
  }
}
