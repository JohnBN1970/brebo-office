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

  /** Create a zone only for an existing building authorized by the caller. */
  public function create(array $zone, \Closure $authorizedBuilding, ?string $requestKey = null): string {
    $buildingId=$zone['building_id']??null;
    $name=$zone['zone_name']??null;
    $lat=$zone['latitude']??null;
    $lon=$zone['longitude']??null;
    $radius=$zone['radius_metres']??null;
    if (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || !is_string($name) || trim($name)==='' || strlen(trim($name))>160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat)>90 || abs((float)$lon)>180 || (float)$radius<=0 || (float)$radius>1000
      || array_key_exists('id',$zone) || array_key_exists('revision',$zone)
      || array_key_exists('project_id',$zone)) throw new InvalidArgumentException('invalid_building_zone');
    if ($requestKey !== null && !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
      throw new InvalidArgumentException('invalid_zone_request_key');
    }
    if ($this->db->inTransaction()) throw new RuntimeException('nested_building_zone_transaction');
    $this->db->beginTransaction();
    try {
      if ($authorizedBuilding($buildingId)!==true) throw new InvalidArgumentException('unauthorized_building_zone');
      $lock=$this->db->prepare('SELECT id FROM office_buildings WHERE id=? FOR UPDATE');
      $lock->execute([$buildingId]);
      if ($lock->fetchColumn()===false) throw new InvalidArgumentException('building_not_found');
      $values=[trim($name),(float)$lat,(float)$lon,(float)$radius,$buildingId];
      $fingerprint=hash('sha256',json_encode([$buildingId,trim($name),sprintf('%.7F',(float)$lat),
        sprintf('%.7F',(float)$lon),sprintf('%.2F',(float)$radius)],JSON_THROW_ON_ERROR));
      if ($requestKey!==null) {
        $existing=$this->db->prepare('SELECT id,building_id,request_fingerprint FROM office_building_zones WHERE request_key=? FOR UPDATE');
        $existing->execute([$requestKey]);
        $saved=$existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($saved)) {
          if ((string)$saved['building_id']!==$buildingId || !hash_equals((string)$saved['request_fingerprint'],$fingerprint)) {
            throw new RuntimeException('zone_request_conflict');
          }
          $this->db->commit();
          return (string)$saved['id'];
        }
        $stmt=$this->db->prepare('INSERT INTO office_building_zones (zone_name,latitude,longitude,radius_metres,building_id,request_key,request_fingerprint) VALUES (?,?,?,?,?,?,?)');
        $values=[...$values,$requestKey,$fingerprint];
      } else {
        $stmt=$this->db->prepare('INSERT INTO office_building_zones (zone_name,latitude,longitude,radius_metres,building_id) VALUES (?,?,?,?,?)');
      }
      $stmt->execute($values);
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

  /** Compare-and-swap update; zone identity and building ownership never change. */
  public function update(string $zoneId, string $buildingId, int $revision, array $changes, \Closure $authorizedBuilding): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$zoneId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || $revision<1 || array_diff(array_keys($changes),['zone_name','latitude','longitude','radius_metres'])!==[]
      || count($changes)!==4) throw new InvalidArgumentException('invalid_zone_update');
    $name=$changes['zone_name'];
    $lat=$changes['latitude'];$lon=$changes['longitude'];$radius=$changes['radius_metres'];
    if (!is_string($name) || trim($name)==='' || strlen(trim($name))>160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat)>90 || abs((float)$lon)>180 || (float)$radius<=0 || (float)$radius>1000)
      throw new InvalidArgumentException('invalid_zone_update');
    if ($this->db->inTransaction()) throw new RuntimeException('nested_building_zone_transaction');
    $this->db->beginTransaction();
    try {
      if (!$authorizedBuilding($buildingId)) throw new InvalidArgumentException('unauthorized_building_zone');
      $stmt=$this->db->prepare('UPDATE office_building_zones SET zone_name=?,latitude=?,longitude=?,radius_metres=?,revision=revision+1 WHERE id=? AND building_id=? AND active=1 AND revision=?');
      $stmt->execute([trim($name),(float)$lat,(float)$lon,(float)$radius,$zoneId,$buildingId,$revision]);
      $updated=$stmt->rowCount()===1;
      $this->db->commit();
      return $updated;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
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
      'SELECT z.id,z.building_id,b.name AS building_name,z.zone_name,z.latitude,z.longitude,z.radius_metres,z.revision
       FROM office_project_buildings pb
       INNER JOIN office_buildings b ON b.id=pb.building_id
       INNER JOIN office_building_zones z ON z.building_id=b.id AND z.active=1
       WHERE pb.project_id=? ORDER BY b.name,z.id'
    );
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Return one active zone only if it belongs to an existing building
   * associated with an authorized project.
   */
  public function forProjectZone(string $projectId,string $zoneId, \Closure $authorizedProject): ?array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$zoneId)
      || $authorizedProject($projectId)!==true) throw new InvalidArgumentException('unauthorized_project_zone');
    $stmt=$this->db->prepare(
      'SELECT z.id,z.building_id,z.zone_name,z.latitude,z.longitude,z.radius_metres,z.revision
       FROM office_project_buildings pb
       INNER JOIN office_buildings b ON b.id=pb.building_id
       INNER JOIN office_building_zones z ON z.building_id=b.id AND z.active=1
       WHERE pb.project_id=? AND z.id=? LIMIT 1'
    );
    $stmt->execute([$projectId,$zoneId]);
    $zone=$stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($zone)?$zone:null;
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
