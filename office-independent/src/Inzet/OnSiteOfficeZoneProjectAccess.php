<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;

/** Isolated zone-map read gate; never replaces the deployed shared project adapter. */
final class OnSiteOfficeZoneProjectAccess {
  public function __construct(private PDO $db) {}

  public function canManage(string $operatorId, int $projectId): bool {
    if ($operatorId === '' || strlen($operatorId)>128 || $projectId<1) return false;
    $query=$this->db->prepare("SELECT 1 FROM office_projects p
      INNER JOIN office_project_memberships m ON m.project_id=p.id
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id
      WHERE p.id=? AND m.operator_id=? AND m.permission='manage' AND a.active=1 LIMIT 1");
    $query->execute([$projectId,$operatorId]);
    return $query->fetchColumn()!==false;
  }
}
