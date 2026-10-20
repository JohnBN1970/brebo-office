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
   * @param array{zone_id:string,project_id:string,active:bool,source_revision:int,zone_name?:string,latitude?:float|int|string,longitude?:float|int|string,radius_metres?:float|int|string} $zone
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
    // Active zones must carry verified geometry; never reuse coordinates from a prior revision.
    $geometry = [$zone['zone_name'] ?? null, $zone['latitude'] ?? null, $zone['longitude'] ?? null, $zone['radius_metres'] ?? null];
    if ($zone['active']) {
      if (!is_string($geometry[0]) || trim($geometry[0]) === '' || strlen($geometry[0]) > 160
        || !is_numeric($geometry[1]) || !is_numeric($geometry[2]) || !is_numeric($geometry[3])
        || !is_finite((float)$geometry[1]) || !is_finite((float)$geometry[2]) || !is_finite((float)$geometry[3])
        || abs((float)$geometry[1]) > 90 || abs((float)$geometry[2]) > 180
        || (float)$geometry[3] <= 0 || (float)$geometry[3] > 5000) {
        throw new InvalidArgumentException('invalid_zone_geometry');
      }
    } else {
      $geometry = [null, null, null, null];
    }
    $query = $this->db->prepare(
      'INSERT INTO office_onsite_canonical_zones
       (zone_id, project_id, active, source_revision, zone_name, latitude, longitude, radius_metres)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         project_id = IF(source_revision < VALUES(source_revision), VALUES(project_id), project_id),
         active = IF(source_revision < VALUES(source_revision), VALUES(active), active),
         zone_name = IF(source_revision < VALUES(source_revision), VALUES(zone_name), zone_name),
         latitude = IF(source_revision < VALUES(source_revision), VALUES(latitude), latitude),
         longitude = IF(source_revision < VALUES(source_revision), VALUES(longitude), longitude),
         radius_metres = IF(source_revision < VALUES(source_revision), VALUES(radius_metres), radius_metres),
         synchronized_at = IF(source_revision < VALUES(source_revision), UTC_TIMESTAMP(), synchronized_at),
         source_revision = GREATEST(source_revision, VALUES(source_revision))'
    );
    $query->execute([$zone['zone_id'],$zone['project_id'],(int)$zone['active'],$zone['source_revision'], ...$geometry]);
    $changed = $query->rowCount() > 0;
    if (!$changed) {
      $read = $this->db->prepare('SELECT project_id, active, source_revision, zone_name, latitude, longitude, radius_metres FROM office_onsite_canonical_zones WHERE zone_id = ?');
      $read->execute([$zone['zone_id']]);
      $current = $read->fetch(PDO::FETCH_ASSOC);
      if (!is_array($current)) throw new RuntimeException('Zone not found after upsert');
      if ((int)$current['source_revision'] === $zone['source_revision']
        && ((string)$current['project_id'] !== $zone['project_id']
          || (int)$current['active'] !== (int)$zone['active']
          || ($zone['active'] && (trim((string)$current['zone_name']) !== trim((string)$geometry[0])
            || abs((float)$current['latitude'] - (float)$geometry[1]) > 0.00000005
            || abs((float)$current['longitude'] - (float)$geometry[2]) > 0.00000005
            || abs((float)$current['radius_metres'] - (float)$geometry[3]) > 0.005)))) {
        throw new RuntimeException('Conflicting zone revision');
      }
    }
    return $changed;
  }
}
