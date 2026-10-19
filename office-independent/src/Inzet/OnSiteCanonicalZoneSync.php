<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Trusted server-side projection writer for existing Drupal brebo_clock_zone.
 * Caller MUST verify Drupal project node IDs map to Office project IDs.
 * Never accepts mobile-device input.
 */
final class OnSiteCanonicalZoneSync {
  public function __construct(private PDO $db) {}

  /**
   * @param array{zone_id:string,project_id:string,active:bool,source_revision:int} $zone
   * @return bool true when a newer source revision was persisted
   */
  public function apply(array $zone): bool {
    foreach (['zone_id','project_id'] as $key) {
      if (!isset($zone[$key]) || !is_string($zone[$key])
        || !preg_match('/^[1-9][0-9]{0,18}$/D',$zone[$key])) {
        throw new InvalidArgumentException('invalid_zone_identity');
      }
    }
    if (!isset($zone['active'],$zone['source_revision'])
      || !is_bool($zone['active']) || !is_int($zone['source_revision'])
      || $zone['source_revision'] < 1) {
      throw new InvalidArgumentException('invalid_zone_revision');
    }
    $query = $this->db->prepare(
      'INSERT INTO office_onsite_canonical_zones
       (zone_id, project_id, active, source_revision)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         project_id = IF(source_revision < VALUES(source_revision), VALUES(project_id), project_id),
         active = IF(source_revision < VALUES(source_revision), VALUES(active), active),
         synchronized_at = IF(source_revision < VALUES(source_revision), UTC_TIMESTAMP(), synchronized_at),
         source_revision = GREATEST(source_revision, VALUES(source_revision))'
    );
    $query->execute([$zone['zone_id'],$zone['project_id'],(int)$zone['active'],$zone['source_revision']]);
    $changed = $query->rowCount() > 0;
    if (!$changed) {
      $read = $this->db->prepare('SELECT project_id, active, source_revision FROM office_onsite_canonical_zones WHERE zone_id = ?');
      $read->execute([$zone['zone_id']]);
      $current = $read->fetch(PDO::FETCH_ASSOC);
      if (!is_array($current)) throw new RuntimeException('Zone not found after upsert');
      if ((int)$current['source_revision'] === $zone['source_revision']
        && ((string)$current['project_id'] !== $zone['project_id']
          || (int)$current['active'] !== (int)$zone['active'])) {
        throw new RuntimeException('Conflicting zone revision');
      }
    }
    return $changed;
  }
}
