<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Office-owned zone creation. Requires trusted project/editor authorization.
 * The identity table is a database-owned sequence, not a Drupal ID mapping.
 * No public endpoint is wired. DDL is review-only.
 */
final class OnSiteOfficeZoneCreator {
  public function __construct(private PDO $db, private OnSiteCanonicalZoneSync $sync, private \Closure $authorizedProject, private ?\Closure $authorizedBuilding = null) {}

  /** @param array<string,mixed> $zone */
  public function create(array $zone): string {
    $projectId = $zone['project_id'] ?? null;
    if (!is_string($projectId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)
      || array_key_exists('zone_id', $zone) || array_key_exists('source_revision', $zone)
      || array_key_exists('expected_revision', $zone)) {
      throw new InvalidArgumentException('invalid_office_zone_create');
    }
    if (($this->authorizedProject)($projectId) !== true) {
      throw new InvalidArgumentException('unauthorized_office_project');
    }
    // Building binding is fail-closed: a client-supplied building cannot be accepted
    // without a trusted server-side project/building membership resolver.
    if (array_key_exists('building_id', $zone)) {
      $buildingId = $zone['building_id'];
      if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId)
        || $this->authorizedBuilding === null
        || ($this->authorizedBuilding)($projectId, $buildingId) !== true) {
        throw new InvalidArgumentException('unauthorized_office_building');
      }
    }
    if ($this->db->inTransaction()) {
      throw new RuntimeException('office_zone_nested_transaction');
    }
    $this->db->beginTransaction();
    try {
      // The unique canonical zone key prevents duplicate IDs. Retry any IDs
      // occupied by existing projections, without ever reusing them.
      for ($attempt = 0; $attempt < 100; $attempt++) {
        $this->db->exec('INSERT INTO office_onsite_zone_identity () VALUES ()');
        $id = $this->db->lastInsertId();
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,18}$/D', $id)) {
          throw new RuntimeException('office_zone_identity_exhausted');
        }
        $check = $this->db->prepare('SELECT 1 FROM office_onsite_canonical_zones WHERE zone_id = ? LIMIT 1');
        $check->execute([$id]);
        if ($check->fetchColumn() !== false) continue;
        $zone['zone_id'] = $id;
        $zone['source_revision'] = 1;
        if (!$this->sync->apply($zone)) {
          throw new RuntimeException('office_zone_create_not_applied');
        }
        $this->db->commit();
        return $id;
      }
      throw new RuntimeException('office_zone_identity_collision_limit');
    } catch (\Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }
}
