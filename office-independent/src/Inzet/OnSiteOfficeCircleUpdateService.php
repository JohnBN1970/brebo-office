<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;
use PDO;

final class OnSiteOfficeCircleUpdateService {
  public function __construct(private PDO $db) {}
  public function update(WebsiteCrmOperatorContext $operator,string $projectId,string $buildingId,
    string $zoneId,int $revision,array $changes): bool {
    if ($operator->operatorId==='' || strlen($operator->operatorId)>128
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$projectId))
      throw new InvalidArgumentException('invalid_zone_update_context');
    $authorize=function(string $candidate) use ($operator,$projectId,$buildingId): bool {
      if ($candidate!==$buildingId) return false;
      $stmt=$this->db->prepare("SELECT b.id FROM office_operator_accounts a
        INNER JOIN office_project_memberships m ON m.operator_id=a.id
        INNER JOIN office_projects p ON p.id=m.project_id
        INNER JOIN office_project_buildings pb ON pb.project_id=p.id
        INNER JOIN office_buildings b ON b.id=pb.building_id
        WHERE a.id=? AND a.active=1 AND m.permission='manage'
          AND p.id=? AND b.id=? LIMIT 1 FOR UPDATE");
      $stmt->execute([$operator->operatorId,$projectId,$candidate]);
      return $stmt->fetchColumn()!==false;
    };
    return (new OfficeBuildingZoneRepository($this->db))->update($zoneId,$buildingId,$revision,$changes,$authorize);
  }
}
