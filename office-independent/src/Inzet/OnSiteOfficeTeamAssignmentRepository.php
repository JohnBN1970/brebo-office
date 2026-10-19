<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Office assignment read adapter. Zone membership must come from a separately
 * verified source; the existing Office Inzet schema contains no zone table.
 * Fail closed until that source is supplied, never assume every project zone.
 */
final class OnSiteOfficeTeamAssignmentRepository implements OnSiteAssignmentRepository {
  /** @param callable(string,string):bool $verifiedZoneMembership */
  public function __construct(private PDO $db, private \Closure $verifiedZoneMembership) {}

  public function isAssigned(string $verifiedEmployeeId, string $projectId, string $zoneId): bool {
    if (!ctype_digit($verifiedEmployeeId) || !ctype_digit($projectId)
      || !ctype_digit($zoneId) || (int)$verifiedEmployeeId < 1
      || (int)$projectId < 1 || (int)$zoneId < 1) return false;
    $statement = $this->db->prepare(
      'SELECT 1 FROM office_inzet_people p
       INNER JOIN office_inzet_project_team t ON t.person_id = p.id
       WHERE p.id = ? AND p.active = 1 AND t.project_id = ? LIMIT 1'
    );
    $statement->execute([$verifiedEmployeeId, $projectId]);
    if ($statement->fetchColumn() === false) return false;
    return ($this->verifiedZoneMembership)($projectId, $zoneId) === true;
  }
}
