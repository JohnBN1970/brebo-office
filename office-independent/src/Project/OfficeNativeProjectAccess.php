<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use Throwable;

/**
 * Independent Office project membership gate.
 * Operator IDs are opaque strings; no implicit integer coercion.
 */
final class OfficeNativeProjectAccess {
  public function __construct(private readonly PDO $db) {}

  public function canRead(string $operatorId, int $projectId): bool {
    return $this->authorized($operatorId, $projectId, false);
  }

  public function canManage(string $operatorId, int $projectId): bool {
    return $this->authorized($operatorId, $projectId, true);
  }

  private function authorized(string $operatorId, int $projectId, bool $manage): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1) return false;
    try {
      $stmt = $this->db->prepare(
        "SELECT 1 FROM office_project_memberships m
         INNER JOIN office_operator_accounts a ON a.id = m.operator_id
         INNER JOIN office_projects p ON p.id = m.project_id
         WHERE m.operator_id = :operator_id AND m.project_id = :project_id
           AND m.permission ".($manage ? "= 'manage'" : "IN ('read','manage')")."
           AND a.active = 1 LIMIT 1"
      );
      $stmt->execute([':operator_id' => $operatorId, ':project_id' => $projectId]);
      return $stmt->fetchColumn() !== false;
    } catch (Throwable) {
      return false;
    }
  }
}
