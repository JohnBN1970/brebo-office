<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only Office assignments for a device-authenticated person.
 * Zones are sourced exclusively from the trusted canonical projection.
 */
final class OnSiteBootstrapRepository {
  public function __construct(private PDO $db) {}

  /** @return array<int,array<string,mixed>> */
  public function forEmployee(string $employeeId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $employeeId)) {
      throw new RuntimeException('Invalid verified employee');
    }
    $statement = $this->db->prepare(
      'SELECT DISTINCT CAST(t.project_id AS CHAR) AS project_id,
              CAST(z.zone_id AS CHAR) AS zone_id,
              z.zone_name, z.latitude, z.longitude, z.radius_metres
       FROM office_inzet_project_team t
       INNER JOIN office_inzet_people p ON p.id = t.person_id AND p.active = 1
       INNER JOIN office_onsite_canonical_zones z ON z.project_id = t.project_id AND z.active = 1
         AND z.synchronized_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
         AND z.latitude BETWEEN -90 AND 90
         AND z.longitude BETWEEN -180 AND 180
         AND z.radius_metres > 0 AND z.radius_metres <= 1000
         AND z.zone_name IS NOT NULL AND z.zone_name <> ''
       WHERE t.person_id = ?
       ORDER BY project_id, zone_id'
    );
    $statement->execute([$employeeId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}
