<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use PDO;

/**
 * Office-owned zone edits. Never imports Drupal fixtures into real projects.
 * The caller must authorize the Office project and editor before invoking.
 */
final class OnSiteOfficeZoneWriter {
  public function __construct(private PDO $db, private OnSiteCanonicalZoneSync $sync) {}

  /** @param array<string,mixed> $zone */
  public function save(array $zone): bool {
    $projectId = $zone['project_id'] ?? null;
    $zoneId = $zone['zone_id'] ?? null;
    if (!is_string($projectId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)
      || !is_string($zoneId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $zoneId)) {
      throw new InvalidArgumentException('invalid_office_zone_identity');
    }
    $query = $this->db->prepare('SELECT 1 FROM office_inzet_projects WHERE id = ? LIMIT 1');
    $query->execute([$projectId]);
    if ($query->fetchColumn() === false) {
      throw new InvalidArgumentException('unknown_office_project');
    }
    $current = $this->db->prepare('SELECT source_revision FROM office_onsite_canonical_zones WHERE zone_id = ?');
    $current->execute([$zoneId]);
    $revision = $current->fetchColumn();
    $zone['source_revision'] = $revision === false ? 1 : ((int) $revision + 1);
    return $this->sync->apply($zone);
  }
}
