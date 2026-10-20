<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Office-owned zone edits. Never imports Drupal fixtures into real projects.
 * A trusted callback must verify the Office project and editor. No public route is wired.
 */
final class OnSiteOfficeZoneWriter {
  public function __construct(private PDO $db, private OnSiteCanonicalZoneSync $sync, private \Closure $authorizedProject) {}

  /** @param array<string,mixed> $zone */
  public function save(array $zone): bool {
    $projectId = $zone['project_id'] ?? null;
    $zoneId = $zone['zone_id'] ?? null;
    if (!is_string($projectId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)
      || !is_string($zoneId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $zoneId)) {
      throw new InvalidArgumentException('invalid_office_zone_identity');
    }
    // Project identity and editor permission must be verified against the actual Office project store.
    // The Inzet schema does not define an office_inzet_projects table.
    if (($this->authorizedProject)($projectId) !== true) {
      throw new InvalidArgumentException('unauthorized_office_project');
    }
    $current = $this->db->prepare('SELECT source_revision FROM office_onsite_canonical_zones WHERE zone_id = ?');
    $current->execute([$zoneId]);
    $revision = $current->fetchColumn();
    $expectedRevision = $zone['expected_revision'] ?? null;
    if (!is_int($expectedRevision) || $expectedRevision < 0 || ($revision === false ? 0 : (int) $revision) !== $expectedRevision) {
      throw new RuntimeException('stale_office_zone_revision');
    }
    $zone['source_revision'] = $expectedRevision + 1;
    return $this->sync->apply($zone);
  }
}
