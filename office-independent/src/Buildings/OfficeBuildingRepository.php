<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Office-native building register. Project relationships are many-to-many.
 * No Drupal services, entities or database connections.
 */
final class OfficeBuildingRepository {
  public function __construct(private PDO $db) {}

  public function create(array $input): string {
    $name=$input['building_name']??null;
    if (!is_string($name) || trim($name)==='' || strlen(trim($name))>160) {
      throw new InvalidArgumentException('invalid_building_name');
    }
    foreach (['street_address'=>255,'postal_code'=>32,'city'=>160] as $key=>$limit) {
      if (isset($input[$key]) && (!is_string($input[$key]) || strlen($input[$key])>$limit)) {
        throw new InvalidArgumentException('invalid_building_address');
      }
    }
    $lat=$input['latitude']??null;
    $lon=$input['longitude']??null;
    if (($lat===null)!==($lon===null)
      || ($lat!==null && (!is_numeric($lat) || !is_numeric($lon)
        || !is_finite((float)$lat) || !is_finite((float)$lon)
        || abs((float)$lat)>90 || abs((float)$lon)>180))) {
      throw new InvalidArgumentException('invalid_building_location');
    }
    $stmt=$this->db->prepare('INSERT INTO office_buildings (building_name,street_address,postal_code,city,latitude,longitude) VALUES (?,?,?,?,?,?)');
    $stmt->execute([trim($name),$input['street_address']??null,$input['postal_code']??null,$input['city']??null,$lat,$lon]);
    $id=$this->db->lastInsertId();
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,18}$/D',$id)) throw new RuntimeException('building_identity_unavailable');
    return $id;
  }

  public function belongsToProject(string $projectId,string $buildingId): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)) return false;
    $stmt=$this->db->prepare('SELECT 1 FROM office_project_buildings pb INNER JOIN office_buildings b ON b.id=pb.building_id AND b.active=1 WHERE pb.project_id=? AND pb.building_id=? LIMIT 1');
    $stmt->execute([$projectId,$buildingId]);
    return $stmt->fetchColumn()!==false;
  }

  public function forProject(string $projectId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)) throw new InvalidArgumentException('invalid_project_id');
    $stmt=$this->db->prepare('SELECT b.id,b.building_name,b.street_address,b.postal_code,b.city,b.latitude,b.longitude FROM office_project_buildings pb INNER JOIN office_buildings b ON b.id=pb.building_id AND b.active=1 WHERE pb.project_id=? ORDER BY b.building_name,b.id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function linkProject(string $projectId,string $buildingId,\Closure $authorizedProject): void {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$buildingId)
      || $authorizedProject($projectId)!==true) throw new InvalidArgumentException('unauthorized_project_building_link');
    $stmt=$this->db->prepare('INSERT INTO office_project_buildings (project_id,building_id) SELECT ?,id FROM office_buildings WHERE id=? AND active=1 ON DUPLICATE KEY UPDATE building_id=VALUES(building_id)');
    $stmt->execute([$projectId,$buildingId]);
    if (!$this->belongsToProject($projectId,$buildingId)) throw new InvalidArgumentException('building_not_found');
  }
}
