<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;
use PDO;

/** Authorized, building-scoped inspection list; no cross-building leakage. */
final class OfficeInspectionOverviewRepository {
  public function __construct(private PDO $db) {}

  public function forBuilding(string $buildingId, string $operatorId): array {
    foreach ([$buildingId,$operatorId] as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new InvalidArgumentException('invalid_inspection_identity');
    }
    $stmt=$this->db->prepare(
      "SELECT i.id,i.building_id,i.project_id,i.title,i.inspection_date,i.status,i.created_at,
              COUNT(DISTINCT f.id) AS finding_count
       FROM office_inspections i
       INNER JOIN office_buildings b ON b.id=i.building_id
       INNER JOIN office_project_buildings pb ON pb.building_id=i.building_id AND pb.project_id=i.project_id
       INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
       LEFT JOIN office_inspection_findings f ON f.inspection_id=i.id
       WHERE i.building_id=? AND m.operator_id=? AND m.permission IN ('read','manage')
       GROUP BY i.id,i.building_id,i.project_id,i.title,i.inspection_date,i.status,i.created_at
       ORDER BY i.inspection_date DESC,i.id DESC LIMIT 100"
    );
    $stmt->execute([$buildingId,$operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function detail(string $inspectionId,string $operatorId): ?array {
    foreach ([$inspectionId,$operatorId] as $id) {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new InvalidArgumentException('invalid_inspection_identity');
    }
    $stmt=$this->db->prepare(
      "SELECT i.id,i.building_id,i.project_id,i.title,i.inspection_date,i.status,
              b.name AS building_name,b.address_line AS street_address,b.city
       FROM office_inspections i
       INNER JOIN office_buildings b ON b.id=i.building_id
       INNER JOIN office_project_buildings pb ON pb.building_id=i.building_id AND pb.project_id=i.project_id
       INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
       WHERE i.id=? AND m.operator_id=? AND m.permission IN ('read','manage') LIMIT 1"
    );
    $stmt->execute([$inspectionId,$operatorId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
  }
}
