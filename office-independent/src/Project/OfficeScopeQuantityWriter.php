<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Test-only quantity line writer. HTTP caller must verify authenticated operator,
 * project manage permission, CSRF, and test-only feature gate.
 */
final class OfficeScopeQuantityWriter {
  public function __construct(private readonly PDO $db) {}

  public function save(int $projectId, int $scopeId, string $code, string $description, string $unit, string $quantity): void {
    $code = trim($code);
    $description = trim($description);
    $unit = trim($unit);
    $quantity = trim($quantity);
    if ($projectId < 1 || $scopeId < 1 || $code === '' || strlen($code) > 128
      || $description === '' || strlen($description) > 255 || $unit === '' || strlen($unit) > 16
      || !preg_match('/^(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,4})?$/D', $quantity)
      || $this->db->inTransaction()) {
      throw new RuntimeException('Ongeldige hoeveelheidsregel.');
    }
    $this->db->beginTransaction();
    try {
      $scope = $this->db->prepare(
        "SELECT s.id FROM office_project_calculation_scopes s
         INNER JOIN office_project_housing_types pht
           ON pht.project_id = s.project_id AND pht.housing_type_id = s.housing_type_id
         WHERE s.id = :scope AND s.project_id = :project
           AND s.scope_kind = 'housing_type' FOR UPDATE"
      );
      $scope->execute([':scope' => $scopeId, ':project' => $projectId]);
      if ($scope->fetchColumn() === false) throw new RuntimeException('Deelcalculatie hoort niet bij dit project.');
      $query = $this->db->prepare(
        'INSERT INTO office_scope_quantity_lines
           (scope_id, line_code, description, unit, quantity_per_dwelling)
         VALUES (:scope, :code, :description, :unit, :quantity)
         ON DUPLICATE KEY UPDATE description = VALUES(description),
           unit = VALUES(unit), quantity_per_dwelling = VALUES(quantity_per_dwelling)'
      );
      $query->execute([
        ':scope' => $scopeId, ':code' => $code, ':description' => $description,
        ':unit' => $unit, ':quantity' => $quantity,
      ]);
      $this->db->commit();
    } catch (Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }
}
