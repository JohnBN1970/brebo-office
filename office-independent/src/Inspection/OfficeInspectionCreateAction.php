<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/**
 * Authenticated application boundary for creating an inspection.
 * Caller must provide a verified Office session, same-origin request and CSRF check.
 */
final class OfficeInspectionCreateAction {
  public function __construct(private PDO $db) {}

  /**
   * @param callable(string,string):bool $canManageBuilding Checks the operator's
   *     manage permission for the selected building/project in Office ACL.
   */
  public function create(array $input, string $operatorId, callable $canManageBuilding): string {
    $buildingId = $input['building_id'] ?? null;
    $projectId = $input['project_id'] ?? null;
    $title = $input['title'] ?? null;
    $date = $input['inspection_date'] ?? null;
    if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId)
      || !is_string($projectId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)
      || !is_string($operatorId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $operatorId)
      || !is_string($title) || !is_string($date)) {
      throw new InvalidArgumentException('invalid_inspection_input');
    }
    if ($canManageBuilding($buildingId, $projectId) !== true) {
      throw new InvalidArgumentException('inspection_access_denied');
    }
    $stmt = $this->db->prepare(
      "SELECT 1 FROM office_project_buildings pb
       INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
       INNER JOIN office_buildings b ON b.id=pb.building_id
       WHERE pb.building_id=? AND pb.project_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1"
    );
    $stmt->execute([$buildingId,$projectId,$operatorId]);
    if ($stmt->fetchColumn() === false) throw new InvalidArgumentException('inspection_access_denied');

    $config = OfficeInspectionReportConfiguration::normalize($input['report_config'] ?? []);
    $ownTransaction = !$this->db->inTransaction();
    if (!$ownTransaction) throw new InvalidArgumentException('inspection_requires_own_transaction');
    $this->db->beginTransaction();
    try {
      $id = (new OfficeInspectionRepository($this->db))->create($buildingId,$projectId,$title,$date,$operatorId);
      (new OfficeInspectionReportConfigurationRepository($this->db))->save($id,$operatorId,$config);
      $this->db->commit();
      return $id;
    } catch (\Throwable $error) {
      $this->db->rollBack();
      throw $error;
    }
  }
}
