<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Native Office housing-type writes. Caller must verify authenticated session,
 * explicit manage membership and CSRF before invoking this service.
 */
final class OfficeHousingTypeWriter {
  public function __construct(private readonly PDO $db) {}

  public function assign(int $projectId, int $buildingId, string $code, string $name, int $count): int {
    $code = trim($code);
    $name = trim($name);
    if ($projectId < 1 || $buildingId < 1 || $count < 1 || $count > 100000
      || $code === '' || strlen($code) > 128 || $name === '' || strlen($name) > 255) {
      throw new RuntimeException('Ongeldige woningtypegegevens.');
    }
    $started = !$this->db->inTransaction();
    if (!$started) {
      throw new RuntimeException('Woningtypebewerking vereist een eigen transactie.');
    }
    $this->db->beginTransaction();
    try {
      // Lock the project-building link: no cross-project or unlinked building writes.
      $link = $this->db->prepare(
        'SELECT 1 FROM office_project_buildings
         WHERE project_id = :project AND building_id = :building FOR UPDATE'
      );
      $link->execute([':project' => $projectId, ':building' => $buildingId]);
      if ($link->fetchColumn() === false) {
        throw new RuntimeException('Gebouw is niet aan dit project gekoppeld.');
      }
      $lookup = $this->db->prepare(
        'SELECT id FROM office_housing_types WHERE building_id = :building AND code = :code FOR UPDATE'
      );
      $lookup->execute([':building' => $buildingId, ':code' => $code]);
      $id = $lookup->fetchColumn();
      if ($id === false) {
        $create = $this->db->prepare(
          'INSERT INTO office_housing_types (building_id, code, name)
           VALUES (:building, :code, :name)'
        );
        $create->execute([':building' => $buildingId, ':code' => $code, ':name' => $name]);
        $id = (int) $this->db->lastInsertId();
      } else {
        $id = (int) $id;
        $update = $this->db->prepare(
          'UPDATE office_housing_types SET name = :name WHERE id = :id AND building_id = :building'
        );
        $update->execute([':name' => $name, ':id' => $id, ':building' => $buildingId]);
      }
      $allocation = $this->db->prepare(
        'INSERT INTO office_project_housing_types (project_id, building_id, housing_type_id, dwelling_count)
         VALUES (:project, :building, :housing_type, :count)
         ON DUPLICATE KEY UPDATE dwelling_count = VALUES(dwelling_count)'
      );
      $allocation->execute([
        ':project' => $projectId, ':building' => $buildingId,
        ':housing_type' => $id, ':count' => $count,
      ]);
      $this->db->commit();
      return $id;
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $exception;
    }
  }
}
