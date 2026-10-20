<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Fail-closed independent Office calculation authorization.
 * Migration is additive; do not activate before it has been applied.
 * A draft calculation may have no project, but must have an owner.
 */
final class WebsiteOfficeCalculationAccessPolicy {

  public function __construct(private readonly PDO $db) {}

  public function requireCalculationAccess(
    WebsiteCrmOperatorContext $operator,
    int $calculationId,
  ): void {
    if ($calculationId <= 0) {
      throw new \DomainException('Valid calculation required.');
    }

    $statement = $this->db->prepare(
      'SELECT 1 FROM office_calculation_registry c
       INNER JOIN office_calculation_permissions p
         ON p.operator_id = :operator_id
       LEFT JOIN office_calculation_project_access a
         ON a.operator_id = p.operator_id
        AND a.project_id = c.project_id
       WHERE c.id = :calculation_id
         AND p.can_calculate = 1
         AND (
           (c.project_id IS NULL AND c.owner_operator_id = p.operator_id)
           OR
           (c.project_id IS NOT NULL AND a.can_calculate = 1)
         )
       LIMIT 1'
    );
    if ($statement === false) {
      throw new \DomainException('Calculation access unavailable.');
    }
    try {
      $executed = $statement->execute([
        ':operator_id' => $operator->operatorId,
        ':calculation_id' => $calculationId,
      ]);
    } catch (\PDOException $error) {
      throw new \DomainException('Calculation access unavailable.', 0, $error);
    }
    if (!$executed) {
      throw new \DomainException('Calculation access unavailable.');
    }
    if ($statement->fetchColumn() === false) {
      throw new \DomainException('Calculation access denied.');
    }
  }
}
