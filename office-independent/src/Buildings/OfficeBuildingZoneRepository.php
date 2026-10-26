<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Building-owned geofences. Projects are only a permission/access path.
 * No project ID is stored on the zone itself.
 */
final class OfficeBuildingZoneRepository {
  public function __construct(private PDO $db) {}

  /** Create a zone only for an active building authorized by the caller. */
  public function create(array $zone, \Closure $authorizedBuilding): string {
    $buildingId=$zone['building_id']??null;
    $name=$zone['zone_name']??null;
    $lat=$zone['latitude']??null;
    $lon=$zone['longitude']??null;
    $radius=$zone['radius_metres']??null;
    if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || !is_string($name) || trim($name)==='' || strlen(trim($name))>160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat)>90 || abs((float)$lon)>180 || (float)$radius<=0 || (float)$radius>5000
      || array_key_exists('id',$zone) || array_key_exists('revision',$zone)
      || array_key_exists('project_id',$zone)) throw new InvalidArgumentException('invalid_building_zone');
    if ($authorizedBuilding($buildingId)!==true) throw new InvalidArgumentException('unauthorized_building_zone');
    if ($this->db->inTransaction()) throw new RuntimeException('nested_building_zone_transaction');
    $this->db->beginTransaction();
    try {
      $stmt=$this->db->prepare('INSERT INTO office_building_zones (building_id,zone_name,latitude,longitude,radius_metres) SELECT id,?,?,?,? FROM office_buildings WHERE id=? AND active=1');
      $stmt->execute([trim($name),(float)$lat,(float)$lon,(float)$radius,$buildingId]);
      if ($stmt->rowCount()!==1) throw new InvalidArgumentException('building_not_found');
      $id=$this->db->lastInsertId();
      if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new RuntimeException('zone_id_unavailable');
      $this->db->commit();
      return $id;
    } catch (\Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }

  /**
   * Project-scoped read through the building relation.
   * The project does not own or duplicate any zone.
   */
  public function forProject(string $projectId, \Closure $authorizedProject): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || $authorizedProject($projectId)!==true) throw new InvalidArgumentException('unauthorized_project_zones');
    $stmt=$this->db->prepare(
      'SELECT z.id,z.building_id,b.building_name,z.zone_name,z.latitude,z.longitude,z.radius_metres,z.revision
       FROM office_project_buildings pb
       INNER JOIN office_buildings b ON b.id=pb.building_id AND b.active=1
       INNER JOIN office_building_zones z ON z.building_id=b.id AND z.active=1
       WHERE pb.project_id=? ORDER BY b.building_name,z.id'
    );
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Return one active zone only if it belongs to an active building
   * associated with an authorized project.
   */
  public function forProjectZone(string $projectId,string $zoneId, \Closure $authorizedProject): ?array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$zoneId)
      || $authorizedProject($projectId)!==true) throw new InvalidArgumentException('unauthorized_project_zone');
    $stmt=$this->db->prepare(
      'SELECT z.id,z.building_id,z.zone_name,z.latitude,z.longitude,z.radius_metres,z.revision
       FROM office_project_buildings pb
       INNER JOIN office_buildings b ON b.id=pb.building_id AND b.active=1
       INNER JOIN office_building_zones z ON z.building_id=b.id AND z.active=1
       WHERE pb.project_id=? AND z.id=? LIMIT 1'
    );
    $stmt->execute([$projectId,$zoneId]);
    $zone=$stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($zone)?$zone:null;
  }

  /**
   * Compare-and-swap edit: only the current revision may be replaced.
   * Caller authorization is rechecked; no project-owned zone copy is created.
   * Returns false for a missing, inactive or concurrently modified zone.
   */
  public function update(string $zoneId, int $expectedRevision, array $changes, \Closure $authorizedBuilding): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $zoneId) || $expectedRevision < 1
      || array_diff(array_keys($changes), ['zone_name','latitude','longitude','radius_metres'])
      || count($changes) !== 4) throw new InvalidArgumentException('invalid_zone_update');
    $name=$changes['zone_name']??null;
    $lat=$changes['latitude']??null;
    $lon=$changes['longitude']??null;
    $radius=$changes['radius_metres']??null;
    if (!is_string($name) || trim($name)==='' || strlen(trim($name))>160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat)>90 || abs((float)$lon)>180 || (float)$radius<=0 || (float)$radius>5000) {
      throw new InvalidArgumentException('invalid_zone_update');
    }
    if ($this->db->inTransaction()) throw new RuntimeException('nested_building_zone_transaction');
    $this->db->beginTransaction();
    try {
      $select=$this->db->prepare('SELECT z.building_id FROM office_building_zones z INNER JOIN office_buildings b ON b.id=z.building_id AND b.active=1 WHERE z.id=? AND z.active=1');
      $select->execute([$zoneId]);
      $buildingId=$select->fetchColumn();
      if ($buildingId===false) { $this->db->rollBack(); return false; }
      if ($authorizedBuilding((string)$buildingId)!==true) throw new InvalidArgumentException('unauthorized_building_zone');
      $stmt=$this->db->prepare('UPDATE office_building_zones SET zone_name=?,latitude=?,longitude=?,radius_metres=?,revision=revision+1 WHERE id=? AND building_id=? AND active=1 AND revision=? AND revision < 18446744073709551615');
      $stmt->execute([trim($name),(float)$lat,(float)$lon,(float)$radius,$zoneId,(string)$buildingId,$expectedRevision]);
      $updated=$stmt->rowCount()===1;
      $this->db->commit();
      return $updated;
    } catch (\Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }

  /**
   * Idempotent creation for a client-generated 64-character lowercase hex request key.
   * The unique database index is the concurrency arbiter. A reused key with
   * different content is rejected rather than silently returning another zone.
   */
  public function createOnce(array $zone, string $requestKey, \Closure $authorizedBuilding): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestKey)) throw new InvalidArgumentException('invalid_zone_request_key');
    if (array_key_exists('request_key', $zone) || array_key_exists('request_fingerprint', $zone)) {
      throw new InvalidArgumentException('invalid_building_zone');
    }
    $buildingId=$zone['building_id']??null;
    $name=$zone['zone_name']??null;
    $lat=$zone['latitude']??null;
    $lon=$zone['longitude']??null;
    $radius=$zone['radius_metres']??null;
    if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || !is_string($name) || trim($name)==='' || strlen(trim($name))>160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat)>90 || abs((float)$lon)>180 || (float)$radius<=0 || (float)$radius>5000
      || array_key_exists('id',$zone) || array_key_exists('revision',$zone)
      || array_key_exists('project_id',$zone)) throw new InvalidArgumentException('invalid_building_zone');
    if ($authorizedBuilding($buildingId)!==true) throw new InvalidArgumentException('unauthorized_building_zone');
    $fingerprint=hash('sha256', json_encode([$buildingId,trim($name),sprintf('%.7f',(float)$lat),sprintf('%.7f',(float)$lon),sprintf('%.2f',(float)$radius)], JSON_THROW_ON_ERROR));
    if ($this->db->inTransaction()) throw new RuntimeException('nested_building_zone_transaction');
    $this->db->beginTransaction();
    try {
      $stmt=$this->db->prepare('INSERT INTO office_building_zones (building_id,zone_name,latitude,longitude,radius_metres,request_key,request_fingerprint) SELECT id,?,?,?,?,?,? FROM office_buildings WHERE id=? AND active=1 ON DUPLICATE KEY UPDATE id=id');
      $stmt->execute([trim($name),(float)$lat,(float)$lon,(float)$radius,$requestKey,$fingerprint,$buildingId]);
      $lookup=$this->db->prepare('SELECT id,building_id,request_fingerprint FROM office_building_zones WHERE request_key=?');
      $lookup->execute([$requestKey]);
      $existing=$lookup->fetch(PDO::FETCH_ASSOC);
      if (!is_array($existing)) throw new InvalidArgumentException('building_not_found');
      if ((string)$existing['building_id']!==$buildingId || !hash_equals((string)$existing['request_fingerprint'],$fingerprint)) {
        throw new InvalidArgumentException('zone_request_key_conflict');
      }
      $this->db->commit();
      return (string)$existing['id'];
    } catch (\Throwable $exception) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $exception;
    }
  }

  /** Only zones for the requested, already-authorized building. */
  public function forBuilding(string $buildingId, \Closure $authorizedBuilding): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || $authorizedBuilding($buildingId)!==true) throw new InvalidArgumentException('unauthorized_building_zone');
    $stmt=$this->db->prepare('SELECT id,building_id,zone_name,latitude,longitude,radius_metres,revision FROM office_building_zones WHERE building_id=? AND active=1 ORDER BY id DESC');
    $stmt->execute([$buildingId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
