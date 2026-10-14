<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Independent Office Calc authorization. Never use CRM edit permission as a
 * substitute for Calc permission. All checks are DB-backed and fail closed.
 *
 * Required additive schema (not present in the verified live Office DB):
 * office_calculation_permissions(operator_id, can_calculate)
 * office_calculation_project_access(operator_id, project_id, can_calculate)
 * office_calculation_registry(id, project_id)
 * This class is inactive until migration and integration tests are approved.
 */
final class WebsiteOfficeCalculationAccessPolicy {

  public function __construct(private readonly PDO $db) {}

  public function requireCalculationAccess(
    WebsiteCrmOperatorContext $operator,
    int $projectId,
    int $calculationId,
  ): void {
    if ($projectId <= 0 || $calculationId <= 0) {
      throw new \DomainException('Valid project and calculation required.');
    }

    // No dynamic identifiers or browser-supplied operator identity.
    $statement = $this->db->prepare(
      'SELECT 1 FROM office_calculation_permissions p
       INNER JOIN office_calculation_project_access a
         ON a.operator_id = p.operator_id
       INNER JOIN office_calculation_registry c
         ON c.project_id = a.project_id
       WHERE p.operator_id = :operator_id
         AND p.can_calculate = 1
         AND a.can_calculate = 1
         AND a.project_id = :project_id
         AND c.id = :calculation_id
       LIMIT 1'
    );
    $statement->execute([
      ':operator_id' => $operator->operatorId,
      ':project_id' => $projectId,
      ':calculation_id' => $calculationId,
    ]);
    if ($statement->fetchColumn() === false) {
      throw new \DomainException('Calculation access denied.');
    }
  }
}
