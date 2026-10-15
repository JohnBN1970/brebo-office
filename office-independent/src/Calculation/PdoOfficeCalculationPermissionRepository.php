<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Fail-closed, read-only Office calculation permission lookup.
 * Provision office_calculation_permissions in a separate reviewed migration.
 */
final class PdoOfficeCalculationPermissionRepository {
  public function __construct(private readonly PDO $db) {}

  public function canCalculate(string $operatorId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      return false;
    }
    $stmt = $this->db->prepare(
      'SELECT 1 FROM office_calculation_permissions p
       INNER JOIN office_operator_accounts a ON a.id = p.operator_id
       WHERE p.operator_id = :operator_id AND p.can_calculate = 1 AND a.active = 1 LIMIT 1'
    );
    $stmt->execute([':operator_id' => $operatorId]);
    return $stmt->fetchColumn() !== false;
  }
}
