<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use DomainException;
use Throwable;

/** Associate an existing authorized calculation with a project housing type. */
final class OfficeHousingCalculationLink {
  public function __construct(private readonly PDO $db) {}

  public function link(int $projectId, int $housingTypeId, int $calculationId): void {
    if ($projectId < 1 || $housingTypeId < 1 || $calculationId < 1) {
      throw new DomainException('Ongeldige koppeling.');
    }
    if ($this->db->inTransaction()) throw new DomainException('Transactie reeds actief.');
    $this->db->beginTransaction();
    try {
    $stmt = $this->db->prepare(
      "UPDATE office_project_calculation_scopes s
       INNER JOIN office_project_housing_types pht
         ON pht.project_id = s.project_id
         AND pht.building_id = s.building_id
         AND pht.housing_type_id = s.housing_type_id
       INNER JOIN office_calc_calculations c
         ON c.project_id = s.project_id AND c.calculation_id = :calculation
       SET s.calc_calculation_id = :linked
       WHERE s.project_id = :project AND s.housing_type_id = :housing
         AND s.scope_kind = 'housing_type' AND s.calc_calculation_id IS NULL
         AND NOT EXISTS (SELECT 1 FROM office_project_calculation_scopes other_scope
           WHERE other_scope.project_id = s.project_id
             AND other_scope.calc_calculation_id = :existing_calculation)"
    );
    $stmt->execute([
      ':calculation' => $calculationId,
      ':linked' => $calculationId,
      ':existing_calculation' => $calculationId,
      ':project' => $projectId,
      ':housing' => $housingTypeId,
    ]);
    if ($stmt->rowCount() !== 1) {
      throw new DomainException('Geen geldige ongekoppelde deelcalculatie gevonden.');
    }
    $this->db->commit();
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
