<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Read-only Office-owned Calc scope lookup. Explicit grants only.
 * Missing project, calculation or grant means deny.
 */
final class OfficeCalcProjectScopeRepository {
  public function __construct(private readonly PDO $db) {}

  public function canLaunch(string $operatorId, int $projectId, int $calculationId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1 || $calculationId < 1) {
      return false;
    }
    $stmt = $this->db->prepare(
      'SELECT 1 FROM office_calc_project_access g
       JOIN office_calc_calculations c ON c.project_id = g.project_id
       JOIN office_operator_accounts a ON a.id = g.operator_id
       WHERE g.operator_id = :operator_id AND g.project_id = :project_id
       AND c.calculation_id = :calculation_id AND a.active = 1 LIMIT 1'
    );
    $stmt->execute([
      ':operator_id' => $operatorId,
      ':project_id' => $projectId,
      ':calculation_id' => $calculationId,
    ]);
    return $stmt->fetchColumn() !== false;
  }
}
