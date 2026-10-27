<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
use InvalidArgumentException;
use PDO;

/**
 * Building owns the zone; Office project membership only grants access.
 */
final class OnSiteOfficeCircleCreateService {
  public function __construct(private PDO $db) {}

  /** @param array<string,mixed> $input */
  public function create(WebsiteCrmOperatorContext $operator, string $projectId, array $input, string $requestKey): string {
    // Building selection is verified against Office membership before writing.
    $allowed = ['building_id', 'zone_name', 'latitude', 'longitude', 'radius_metres'];
    if (array_diff(array_keys($input), $allowed) !== []) {
      throw new InvalidArgumentException('office_circle_unknown_fields');
    }
    if (($operator->operatorId === '' || strlen($operator->operatorId) > 128)
      || !preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)) {
      throw new InvalidArgumentException('invalid_office_circle_context');
    }
    $authorize = function (string $buildingId) use ($operator, $projectId): bool {
      // Lock the authoritative records in the repository transaction: account
      // deactivation or membership revocation cannot race a successful insert.
      $query = $this->db->prepare("SELECT b.id FROM office_operator_accounts a
        INNER JOIN office_project_memberships m ON m.operator_id=a.id
        INNER JOIN office_projects p ON p.id=m.project_id
        INNER JOIN office_project_buildings pb ON pb.project_id=p.id
        INNER JOIN office_buildings b ON b.id=pb.building_id
        WHERE a.id=? AND a.active=1 AND m.permission='manage'
          AND p.id=? AND b.id=? LIMIT 1 FOR UPDATE");
      $query->execute([$operator->operatorId, $projectId, $buildingId]);
      return $query->fetchColumn() !== false;
    };
    return (new OfficeBuildingZoneRepository($this->db))->createOnce($input, $requestKey, $authorize);
  }
}
