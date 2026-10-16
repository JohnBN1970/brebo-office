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
      // Lock the project calculation and scope rows before changing any link.
      $calculation = $this->db->prepare(
        'SELECT calculation_id FROM office_calc_calculations
         WHERE project_id = :project AND calculation_id = :calculation FOR UPDATE'
      );
      $calculation->execute([':project' => $projectId, ':calculation' => $calculationId]);
      if ($calculation->fetchColumn() === false) {
        throw new DomainException('Calculatie hoort niet bij dit project.');
      }
      $scopes = $this->db->prepare(
        'SELECT id, housing_type_id, building_id, calc_calculation_id
         FROM office_project_calculation_scopes
         WHERE project_id = :project ORDER BY id FOR UPDATE'
      );
      $scopes->execute([':project' => $projectId]);
      $targetId = null;
      foreach ($scopes->fetchAll(PDO::FETCH_ASSOC) as $scope) {
        if ($scope['calc_calculation_id'] !== null
          && (int) $scope['calc_calculation_id'] === $calculationId) {
          throw new DomainException('Calculatie is al gekoppeld.');
        }
        if ($scope['housing_type_id'] !== null
          && (int) $scope['housing_type_id'] === $housingTypeId
          && $scope['calc_calculation_id'] !== null) {
          throw new DomainException('Woningtype heeft al een calculatie.');
        }
        if ($scope['housing_type_id'] !== null
          && (int) $scope['housing_type_id'] === $housingTypeId
          && $scope['calc_calculation_id'] === null) {
          $targetId = (int) $scope['id'];
        }
      }
      if ($targetId === null) throw new DomainException('Woningtype heeft geen vrije deelcalculatie.');
      $update = $this->db->prepare(
        "UPDATE office_project_calculation_scopes s
         INNER JOIN office_project_housing_types pht
           ON pht.project_id = s.project_id
           AND pht.building_id = s.building_id
           AND pht.housing_type_id = s.housing_type_id
         SET s.calc_calculation_id = :calculation
         WHERE s.id = :scope AND s.project_id = :project
           AND s.scope_kind = 'housing_type' AND s.calc_calculation_id IS NULL"
      );
      $update->execute([':calculation' => $calculationId, ':scope' => $targetId, ':project' => $projectId]);
      if ($update->rowCount() !== 1) throw new DomainException('Koppeling niet uitgevoerd.');
    $this->db->commit();
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
