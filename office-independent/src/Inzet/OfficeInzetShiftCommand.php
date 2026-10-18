<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Re-checks live Office permissions and project-team membership for each write.
 * The caller's supplied project/person arrays are never trusted as authorization.
 */
final class OfficeInzetShiftCommand {
  public function __construct(private PDO $db, private OfficeInzetShiftWriter $writer) {}

  public function createForOperator(int $operatorId, array $request): int {
    if ($operatorId < 1) throw new RuntimeException('Operator not authenticated');
    $projectId = filter_var($request['project_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $personId = filter_var($request['person_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!is_int($projectId) || !is_int($personId)) throw new RuntimeException('Invalid planning selection');
    $permission = $this->db->prepare("SELECT 1 FROM office_project_memberships m
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage' AND a.active = 1 LIMIT 1");
    $permission->execute([$operatorId,$projectId]);
    if (!$permission->fetchColumn()) throw new RuntimeException('Project management permission required');
    $team = $this->db->prepare('SELECT 1 FROM office_inzet_project_team t
      INNER JOIN office_inzet_people p ON p.id = t.person_id
      WHERE t.project_id = ? AND t.person_id = ? AND p.active = 1 LIMIT 1');
    $team->execute([$projectId,$personId]);
    if (!$team->fetchColumn()) throw new RuntimeException('Employee not eligible for project');
    return $this->writer->create($request,[$projectId],[$personId]);
  }
}
