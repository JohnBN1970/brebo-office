<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
use RuntimeException;

/**
 * Reads map coordinates only for an active Office operator with project manage rights.
 * The optional Office-native location columns must exist before this is wired to a route.
 */
final class OnSiteOfficeProjectMapRepository {
  public function __construct(private PDO $db) {}

  /** @return array{project_id:string,latitude:float,longitude:float} */
  public function forManager(int $operatorId, int $projectId): array {
    if ($operatorId < 1 || $projectId < 1) throw new RuntimeException('office_project_map_access_denied');
    $statement=$this->db->prepare(
      "SELECT p.id, p.latitude, p.longitude
       FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE p.id=:project_id AND m.operator_id=:operator_id
         AND m.permission='manage' AND a.active=1
       LIMIT 1"
    );
    $statement->execute([':project_id'=>$projectId,':operator_id'=>$operatorId]);
    $project=$statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($project)) throw new RuntimeException('office_project_map_access_denied');
    return OnSiteOfficeProjectMapSeed::fromAuthorizedProject($project);
  }
}
