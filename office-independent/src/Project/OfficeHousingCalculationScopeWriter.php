<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Creates a project-local housing-type calculation scope, without granting Calc access.
 * A scope is a planning container, not an actual calculation or an authorization token.
 */
final class OfficeHousingCalculationScopeWriter {
  public function __construct(private readonly PDO $db) {}

  public function create(int $projectId, int $housingTypeId): int {
    if ($projectId < 1 || $housingTypeId < 1 || $this->db->inTransaction()) {
      throw new RuntimeException('Ongeldige deelcalculatieaanvraag.');
    }
    $this->db->beginTransaction();
    try {
      $allocation = $this->db->prepare(
        'SELECT pht.building_id, ht.name, ht.code
         FROM office_project_housing_types pht
         INNER JOIN office_housing_types ht ON ht.id = pht.housing_type_id
           AND ht.building_id = pht.building_id
         WHERE pht.project_id = :project AND pht.housing_type_id = :housing
         FOR UPDATE'
      );
      $allocation->execute([':project' => $projectId, ':housing' => $housingTypeId]);
      $row = $allocation->fetch(PDO::FETCH_ASSOC);
      if (!is_array($row)) {
        throw new RuntimeException('Woningtype hoort niet bij dit project.');
      }
      $existing = $this->db->prepare(
        "SELECT id FROM office_project_calculation_scopes
         WHERE project_id = :project AND housing_type_id = :housing
           AND scope_kind = 'housing_type' LIMIT 1 FOR UPDATE"
      );
      $existing->execute([':project' => $projectId, ':housing' => $housingTypeId]);
      $id = $existing->fetchColumn();
      if ($id !== false) {
        $this->db->commit();
        return (int) $id;
      }
      $insert = $this->db->prepare(
        "INSERT INTO office_project_calculation_scopes
          (project_id, building_id, housing_type_id, scope_kind, scope_label)
         VALUES (:project, :building, :housing, 'housing_type', :label)"
      );
      $insert->execute([
        ':project' => $projectId,
        ':building' => (int) $row['building_id'],
        ':housing' => $housingTypeId,
        ':label' => (string) $row['name'] . ' (' . (string) $row['code'] . ')',
      ]);
      $id = (int) $this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }
}
