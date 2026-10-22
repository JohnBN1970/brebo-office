<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only native Office OnSite bootstrap projection.
 * A building owns its zones; project membership only grants access.
 * This adapter does not issue device credentials or mutate assignments.
 */
final class OnSiteNativeBootstrapRepository {
  public function __construct(private PDO $db) {}

  /** @return array<int,array<string,mixed>> */
  public function forEmployee(string $employeeId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $employeeId)) {
      throw new RuntimeException('Invalid verified employee');
    }
    $statement = $this->db->prepare(
      'SELECT DISTINCT CAST(t.project_id AS CHAR) AS project_id,
              p.title AS project_name,
              CAST(z.id AS CHAR) AS zone_id,
              z.zone_name, z.latitude, z.longitude, z.radius_metres
       FROM office_inzet_project_team t
       INNER JOIN office_inzet_people person ON person.id=t.person_id AND person.active=1
       INNER JOIN office_projects p ON p.id=t.project_id
       INNER JOIN office_project_buildings pb ON pb.project_id=p.id
       INNER JOIN office_buildings b ON b.id=pb.building_id
       INNER JOIN office_building_zones z ON z.building_id=b.id AND z.active=1
       WHERE t.person_id=?
         AND z.latitude BETWEEN -90 AND 90
         AND z.longitude BETWEEN -180 AND 180
         AND z.radius_metres > 0 AND z.radius_metres <= 1000
         AND z.zone_name IS NOT NULL AND CHAR_LENGTH(TRIM(z.zone_name)) > 0
       ORDER BY project_id, zone_id'
    );
    $statement->execute([$employeeId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}
