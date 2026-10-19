<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only projection of the existing Drupal brebo_clock_zone identity.
 * Only a trusted sync process may populate this table after source verification.
 */
final class OnSiteCanonicalZoneRepository {
  public function __construct(private PDO $db) {}

  public function isActiveForProject(string $projectId, string $zoneId): bool {
    if (!ctype_digit($projectId) || !ctype_digit($zoneId)
      || (int)$projectId < 1 || (int)$zoneId < 1) return false;
    $statement = $this->db->prepare(
      'SELECT 1 FROM office_onsite_canonical_zones
       WHERE zone_id = ? AND project_id = ? AND active = 1 AND synchronized_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
         AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180
         AND radius_metres > 0 AND radius_metres <= 1000
         AND zone_name IS NOT NULL AND CHAR_LENGTH(TRIM(zone_name)) > 0 LIMIT 1'
    );
    $statement->execute([$zoneId,$projectId]);
    return $statement->fetchColumn() !== false;
  }

  public function callback(): \Closure {
    return fn(string $projectId,string $zoneId): bool => $this->isActiveForProject($projectId,$zoneId);
  }
}
